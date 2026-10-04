<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\ExpenseCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseDocumentaryReviewTest extends TestCase
{
    use RefreshDatabase;

    private function expense(string $number): Expense
    {
        $expense = Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'created_by' => null,
            'number' => $number, 'type' => 'invoice', 'document_number' => $number, 'issuer_legal_name' => 'Supplier',
            'expense_date' => '2026-09-15', 'currency_code' => 'EUR', 'tax_regime' => 'standard', 'subtotal' => 100,
            'tax_amount' => 21, 'withholding_rate' => 0, 'withholding_amount' => 0, 'amount' => 121, 'paid_amount' => 0, 'status' => 'pending']);
        $expense->taxLines()->create(['kind' => 'vat', 'taxable_base' => 100, 'tax_rate' => 21,
            'tax_amount' => 21, 'deductible_percent' => 100, 'deductible_amount' => 21, 'position' => 1]);

        return $expense;
    }

    public function test_admin_sees_eligible_and_protected_historical_expenses(): void
    {
        config(['fiscal.grouped_expense_calculation' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $eligible = $this->expense('EXP-REVIEW-1');
        $paid = $this->expense('EXP-REVIEW-2');
        SupplierPayment::create(['expense_id' => $paid->id, 'created_by' => $admin->id, 'amount' => 10, 'method' => 'cash', 'paid_at' => '2026-09-15']);

        $this->actingAs($admin)->get(route('expenses.documentary-review'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('expenses/documentary-review')
                ->where('review.total', 2)
                ->where('review.data.0.number', 'EXP-REVIEW-2')
                ->where('review.data.0.eligible', false)
                ->where('review.data.0.comparison.paid', '10.00')
                ->where('review.data.0.comparison.outstanding', '111.00')
                ->where('review.data.1.number', 'EXP-REVIEW-1')
                ->where('review.data.1.eligible', true)
                ->where('review.data.1.edit_url', route('expenses.edit', ['expense' => $eligible, 'documentary_review' => 1])));

        $this->actingAs($admin)->get(route('expenses.documentary-review', ['filter' => 'with_payments']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('filter', 'with_payments')->where('review.total', 1)
                ->where('review.data.0.number', 'EXP-REVIEW-2'));
    }

    public function test_review_requires_enabled_feature_and_admin_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $expense = $this->expense('EXP-REVIEW-AUTH');
        config(['fiscal.grouped_expense_calculation' => false]);
        $this->actingAs($admin)->get(route('expenses.documentary-review'))->assertForbidden();
        $this->post(route('expenses.documentary-evidence.adopt', $expense), [
            'revision' => ExpenseCorrection::revision($expense), 'documentary_evidence_reason' => 'Historical evidence check',
        ])->assertForbidden();
        config(['fiscal.grouped_expense_calculation' => true]);
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        $this->get(route('expenses.documentary-review'))->assertForbidden();
        $this->post(route('expenses.documentary-evidence.adopt', $expense), [
            'revision' => ExpenseCorrection::revision($expense), 'documentary_evidence_reason' => 'Historical evidence check',
        ])->assertForbidden();
    }

    public function test_admin_can_attach_matching_evidence_to_paid_expense_without_financial_changes(): void
    {
        config(['fiscal.grouped_expense_calculation' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $expense = $this->expense('EXP-PAID-EVIDENCE');
        SupplierPayment::create(['expense_id' => $expense->id, 'created_by' => $admin->id, 'amount' => 121,
            'method' => 'cash', 'paid_at' => '2026-09-15']);
        $expense->update(['status' => 'paid', 'paid_amount' => 121]);
        $before = $expense->only(['subtotal', 'tax_amount', 'amount', 'paid_amount', 'status']);
        $entries = JournalEntry::count();

        $this->actingAs($admin)->post(route('expenses.documentary-evidence.adopt', $expense), [
            'revision' => ExpenseCorrection::revision($expense->fresh()),
            'documentary_evidence_reason' => 'Reviewed against the original supplier invoice',
        ])->assertSessionHasNoErrors();

        $expense->refresh();
        $this->assertSame($before, $expense->only(array_keys($before)));
        $this->assertSame('supplier-vat-v1', $expense->tax_calculation_version);
        $this->assertNotNull($expense->tax_calculation_snapshot);
        $this->assertSame(1, $expense->payments()->count());
        $this->assertSame($entries, JournalEntry::count());
        $this->assertSame(1, AuditLog::where('event', 'expense_documentary_evidence_adopted')->count());
    }

    public function test_evidence_adoption_rejects_stale_or_non_matching_documents(): void
    {
        config(['fiscal.grouped_expense_calculation' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $expense = $this->expense('EXP-MISMATCH-EVIDENCE');
        $revision = ExpenseCorrection::revision($expense);
        $expense->update(['amount' => 120]);

        $this->actingAs($admin)->post(route('expenses.documentary-evidence.adopt', $expense), [
            'revision' => $revision, 'documentary_evidence_reason' => 'Historical evidence check',
        ])->assertSessionHasErrors('documentary_evidence_reason');
        $this->assertNull($expense->fresh()->tax_calculation_version);

        $this->post(route('expenses.documentary-evidence.adopt', $expense), [
            'revision' => ExpenseCorrection::revision($expense), 'documentary_evidence_reason' => 'Historical evidence check',
        ])->assertSessionHasErrors('documentary_evidence_reason');
        $this->assertNull($expense->fresh()->tax_calculation_version);
    }
}
