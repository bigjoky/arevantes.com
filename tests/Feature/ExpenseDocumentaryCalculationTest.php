<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\ExpenseCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ExpenseDocumentaryCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function previewToken(array $payload): ?string
    {
        if (! config('fiscal.grouped_expense_calculation') || ! auth()->user()->hasRole('admin') || ! isset($payload['calculation_mode'])) {
            return null;
        }
        $response = $this->postJson(route('expenses.calculation.preview'), $payload);

        return $response->isOk() ? $response->json('preview_token') : null;
    }

    private function storeWithPreview(array $payload): TestResponse
    {
        return $this->post(route('expenses.store'), [...$payload, 'calculation_preview_token' => $this->previewToken($payload)]);
    }

    private function updateWithPreview(Expense $expense, array $payload): TestResponse
    {
        return $this->patch(route('expenses.update', $expense), [...$payload, 'calculation_preview_token' => $this->previewToken($payload)]);
    }

    private function payload(): array
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        return ['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => 'invoice',
            'document_number' => 'DECIMAL-1', 'issuer_legal_name' => 'Supplier', 'issuer_tax_id' => 'A12345678',
            'expense_date' => '2026-09-15', 'calculation_mode' => 'grouped_v1', 'calculation_reason' => 'Documentary supplier quota',
            'tax_lines' => [['calculation_id' => 'line-a', 'kind' => 'vat', 'taxable_base' => '3.46', 'tax_rate' => '10', 'tax_amount' => '0.34', 'deductible_percent' => '100']]];
    }

    public function test_flag_is_off_and_legacy_flow_does_not_gain_a_snapshot(): void
    {
        config(['fiscal.grouped_expense_calculation' => false]);
        $payload = $this->payload();
        $this->storeWithPreview($payload)->assertSessionHasErrors('calculation_mode');
        $this->assertSame(0, Expense::count());
        unset($payload['calculation_mode']);
        $payload['tax_calculation_snapshot'] = ['forged' => true];
        $this->storeWithPreview($payload)->assertSessionHasNoErrors();
        $this->assertNull(Expense::sole()->tax_calculation_snapshot);
        $this->assertNull(Expense::sole()->tax_calculation_version);
    }

    public function test_documentary_quota_is_persisted_with_version_and_verifiable_evidence(): void
    {
        config(['fiscal.grouped_expense_calculation' => true]);
        $payload = $this->payload();
        $payload['tax_calculation_snapshot'] = ['forged' => true];
        $this->storeWithPreview($payload)->assertSessionHasNoErrors();
        $expense = Expense::sole();
        $this->assertSame('3.80', $expense->amount);
        $this->assertSame('0.34', $expense->tax_amount);
        $this->assertSame('supplier-vat-v1', $expense->tax_calculation_version);
        $snapshot = $expense->tax_calculation_snapshot;
        $digest = $snapshot['sha256'];
        unset($snapshot['sha256']);
        $this->assertSame($digest, hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)));
        $this->assertSame('-0.01', $snapshot['result']['tax_groups'][0]['tax_adjustment']);
        $this->assertSame('0.34', $expense->taxLines->sole()->deductible_amount);
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_deduction_is_grouped_and_the_evidence_is_preserved_in_corrections(): void
    {
        config(['fiscal.grouped_expense_calculation' => true]);
        $payload = $this->payload();
        $payload['tax_lines'] = array_map(fn ($id) => ['calculation_id' => $id, 'kind' => 'vat', 'taxable_base' => '0.05', 'tax_rate' => '21', 'tax_amount' => '0.01', 'deductible_percent' => '50'], ['a', 'b']);
        $this->storeWithPreview($payload)->assertSessionHasNoErrors();
        $expense = Expense::sole();
        $this->assertEquals(.01, $expense->taxLines()->sum('deductible_amount'));
        $oldEvidence = $expense->tax_calculation_snapshot;
        $this->updateWithPreview($expense, [...$payload, 'revision' => ExpenseCorrection::revision($expense),
            'correction_reason' => 'Add an explanatory note', 'notes' => 'Reviewed'])->assertSessionHasNoErrors();
        $this->assertSame($oldEvidence, AuditLog::where('event', 'expense_corrected')->sole()->old_values['tax_calculation_snapshot']);
        $this->assertSame($oldEvidence, $expense->fresh()->tax_calculation_snapshot);
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_existing_version_cannot_silently_fall_back_when_disabled(): void
    {
        config(['fiscal.grouped_expense_calculation' => true]);
        $payload = $this->payload();
        $this->storeWithPreview($payload)->assertSessionHasNoErrors();
        $expense = Expense::sole();
        config(['fiscal.grouped_expense_calculation' => false]);
        unset($payload['calculation_mode']);
        $this->updateWithPreview($expense, [...$payload, 'revision' => ExpenseCorrection::revision($expense), 'correction_reason' => 'Attempt legacy fallback'])->assertSessionHasErrors('calculation_mode');
        $this->assertSame('3.80', $expense->fresh()->amount);
    }

    public function test_unsupported_regimes_and_unjustified_group_rounding_are_rejected(): void
    {
        config(['fiscal.grouped_expense_calculation' => true]);
        $payload = $this->payload();
        $this->storeWithPreview([...$payload, 'tax_regime' => 'reverse_charge'])->assertSessionHasErrors('calculation_mode');
        unset($payload['calculation_reason']);
        $this->storeWithPreview($payload)->assertSessionHasErrors('calculation_mode');
        $this->assertSame(0, Expense::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_storage_overflow_and_non_admin_opt_in_are_rejected(): void
    {
        config(['fiscal.grouped_expense_calculation' => true]);
        $payload = $this->payload();
        $payload['tax_lines'][0] = ['calculation_id' => 'a', 'kind' => 'vat', 'taxable_base' => '9999999999.99', 'tax_rate' => '21', 'tax_amount' => '2099999999.99', 'deductible_percent' => '100'];
        $this->storeWithPreview($payload)->assertSessionHasErrors('calculation_mode');
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        $response = $this->storeWithPreview($payload);
        $this->assertContains($response->status(), [302, 403]);
        if ($response->status() === 302) {
            $response->assertSessionHasErrors('calculation_mode');
        }
        $this->assertSame(0, Expense::count());
    }

    public function test_migration_rollback_cannot_destroy_calculation_evidence(): void
    {
        config(['fiscal.grouped_expense_calculation' => true]);
        $this->storeWithPreview($this->payload())->assertSessionHasNoErrors();
        $migration = require database_path('migrations/2026_09_29_000000_add_expense_calculation_evidence.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot remove persisted expense calculation evidence.');
        $migration->down();
    }

    public function test_failed_accounting_rolls_back_the_expense_and_its_snapshot(): void
    {
        config(['fiscal.grouped_expense_calculation' => true]);
        $payload = $this->payload();
        ExpenseCategory::findOrFail($payload['expense_category_id'])->update(['expense_account_code' => 'MISSING-ACCOUNT']);
        $response = $this->storeWithPreview($payload);
        $this->assertGreaterThanOrEqual(400, $response->status());
        $this->assertSame(0, Expense::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_preview_is_read_only_and_changed_or_expired_tokens_cannot_save(): void
    {
        config(['fiscal.grouped_expense_calculation' => true]);
        $payload = $this->payload();
        $token = $this->postJson(route('expenses.calculation.preview'), $payload)->assertOk()
            ->assertJsonPath('result.totals.total', '3.80')->json('preview_token');
        $this->assertSame(0, Expense::count());
        $this->assertSame(0, JournalEntry::count());
        $this->post(route('expenses.store'), $payload)->assertSessionHasErrors('calculation_mode');
        $changed = $payload;
        $changed['tax_lines'][0]['taxable_base'] = '3.47';
        $this->post(route('expenses.store'), [...$changed, 'calculation_preview_token' => $token])->assertSessionHasErrors('calculation_mode');
        $this->travel(16)->minutes();
        $this->post(route('expenses.store'), [...$payload, 'calculation_preview_token' => $token])->assertSessionHasErrors('calculation_mode');
        $this->travelBack();
        $this->assertSame(0, Expense::count());
    }

    public function test_preview_requires_enabled_feature_admin_and_bounded_lines(): void
    {
        $payload = $this->payload();
        config(['fiscal.grouped_expense_calculation' => false]);
        $this->postJson(route('expenses.calculation.preview'), $payload)->assertForbidden();
        config(['fiscal.grouped_expense_calculation' => true]);
        $this->postJson(route('expenses.calculation.preview'), [...$payload, 'tax_lines' => array_fill(0, 21, $payload['tax_lines'][0])])->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        $this->postJson(route('expenses.calculation.preview'), $payload)->assertForbidden();
    }
}
