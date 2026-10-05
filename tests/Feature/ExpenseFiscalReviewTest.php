<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseFiscalClassification;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\SupplierPayment;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Tax\ExpenseDocumentaryCalculation;
use App\Services\Tax\ExpenseFiscalReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseFiscalReviewTest extends TestCase
{
    use RefreshDatabase;

    private function expense(): Expense
    {
        $calculation = app(ExpenseDocumentaryCalculation::class)->calculate([
            'currency_code' => 'EUR', 'tax_regime' => 'standard', 'withholding_rate' => '0', 'withholding_amount' => '0',
            'tax_lines' => [['calculation_id' => 'line-1', 'kind' => 'vat', 'taxable_base' => '100', 'tax_rate' => '21', 'tax_amount' => '21', 'deductible_percent' => '50']],
        ], 'Supplier documentary quota');
        $lines = $calculation['tax_lines'];
        unset($calculation['tax_lines']);
        $expense = Expense::create([...$calculation, 'expense_category_id' => ExpenseCategory::firstOrFail()->id,
            'number' => 'EXP-FISCAL-1', 'type' => 'invoice', 'document_number' => 'SUP-123',
            'issuer_legal_name' => 'Supplier SA', 'issuer_tax_id' => 'A12345678', 'issuer_country' => 'ES',
            'expense_date' => '2026-09-15', 'currency_code' => 'EUR', 'tax_regime' => 'standard', 'status' => 'pending']);
        $expense->taxLines()->createMany($lines);
        FiscalYear::updateOrCreate(['year' => 2026], ['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        return $expense->fresh();
    }

    private function payload(Expense $expense, string $class = 'domestic_current'): array
    {
        return ['revision' => app(ExpenseFiscalReview::class)->revision($expense->fresh()),
            'operation_class' => $class, 'reason' => 'Invoice, VAT territory and deduction period checked', 'confirmed' => true,
            'source_reference' => 'Supplier invoice SUP-123', 'registered_on' => '2026-09-15', 'deduction_year' => 2026, 'deduction_quarter' => 3];
    }

    public function test_saves_audited_review_without_mutating_paid_invoice_or_accounting(): void
    {
        $expense = $this->expense();
        $expense->update(['status' => 'paid', 'paid_amount' => 121]);
        SupplierPayment::create(['expense_id' => $expense->id, 'created_by' => auth()->id(), 'amount' => 121, 'method' => 'cash', 'paid_at' => '2026-09-15']);
        $before = $expense->fresh()->attributesToArray();
        $lines = $expense->taxLines()->get()->toArray();
        $entries = JournalEntry::count();
        $payments = SupplierPayment::count();
        $this->post(route('tax.expenses.classification.update', $expense), $this->payload($expense))->assertRedirect()->assertSessionHasNoErrors();
        $review = $expense->fresh()->fiscalClassification;
        $this->assertSame('domestic_current', $review->operation_class);
        $this->assertEquals(auth()->id(), $review->reviewed_by);
        $this->assertTrue(app(ExpenseFiscalReview::class)->isCurrent($expense->fresh()));
        $this->assertSame($before, $expense->fresh()->attributesToArray());
        $this->assertSame($lines, $expense->taxLines()->get()->toArray());
        $this->assertSame($entries, JournalEntry::count());
        $this->assertSame($payments, SupplierPayment::count());
        $this->assertSame(0, TaxReturn::count());
        $log = AuditLog::where('event', 'expense_fiscal_classified')->sole();
        $this->assertSame($review->evidence_fingerprint, $log->new_values['evidence_fingerprint']);
        $this->assertSame(auth()->user()->name, $log->new_values['evidence_snapshot']['reviewer']['name']);
    }

    public function test_stale_evidence_and_stale_review_cannot_overwrite_classification(): void
    {
        $expense = $this->expense();
        $stale = $this->payload($expense);
        $expense->update(['issuer_legal_name' => 'Corrected Supplier']);
        $this->post(route('tax.expenses.classification.update', $expense), $stale)->assertSessionHasErrors('operation_class');
        $fresh = $this->payload($expense);
        $this->post(route('tax.expenses.classification.update', $expense), $fresh)->assertSessionHasNoErrors();
        $this->post(route('tax.expenses.classification.update', $expense), [...$fresh, 'operation_class' => 'domestic_investment'])->assertSessionHasErrors('operation_class');
        $expense->update(['document_number' => 'NEW-123']);
        $this->assertFalse(app(ExpenseFiscalReview::class)->isCurrent($expense->fresh()));
        $this->actingAs(auth()->user())->get('/tax/models/303?year=2026&quarter=3')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('preview.deductions.0.base', null)->has('preview.pendingReceived', 1));
    }

    public function test_valid_classification_maps_full_base_and_only_deductible_quota(): void
    {
        $expense = $this->expense();
        $this->post(route('tax.expenses.classification.update', $expense), $this->payload($expense, 'domestic_investment'))->assertSessionHasNoErrors();
        $this->get('/tax/models/303?year=2026&quarter=3')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('preview.deductions.1.base_box', '30')->where('preview.deductions.1.vat_box', '31')
            ->where('preview.deductions.1.base', 100)->where('preview.deductions.1.vat', 10.5)->has('preview.pendingReceived', 0));
        $this->get(route('tax.expenses.classification.show', $expense))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('tax/expense-classification')->where('current', true)->has('history', 1));
    }

    public function test_returning_to_pending_keeps_history_and_removes_box_assignment(): void
    {
        $expense = $this->expense();
        $this->post(route('tax.expenses.classification.update', $expense), $this->payload($expense))->assertSessionHasNoErrors();
        $this->post(route('tax.expenses.classification.update', $expense), $this->payload($expense, 'pending'))->assertSessionHasNoErrors();
        $this->assertFalse(app(ExpenseFiscalReview::class)->isCurrent($expense->fresh()));
        $this->assertSame(2, AuditLog::where('event', 'expense_fiscal_classified')->count());
        $this->assertSame(1, ExpenseFiscalClassification::count());
    }

    public function test_requires_confirmation_and_rejects_missing_evidence_or_special_regime(): void
    {
        $expense = $this->expense();
        $this->post(route('tax.expenses.classification.update', $expense), [...$this->payload($expense), 'confirmed' => false])->assertSessionHasErrors('operation_class');
        $expense->update(['tax_calculation_snapshot' => []]);
        $this->post(route('tax.expenses.classification.update', $expense), $this->payload($expense))->assertSessionHasErrors('operation_class');
        $expense->update(['tax_regime' => 'reverse_charge']);
        $this->post(route('tax.expenses.classification.update', $expense), $this->payload($expense))->assertSessionHasErrors('operation_class');
        $this->assertSame(0, ExpenseFiscalClassification::count());
    }

    public function test_tax_lines_must_match_calculation_evidence_not_just_invoice_totals(): void
    {
        $expense = $this->expense();
        $expense->taxLines()->update(['deductible_amount' => 11]);
        $this->post(route('tax.expenses.classification.update', $expense), $this->payload($expense))->assertSessionHasErrors('operation_class');
        $this->assertSame(0, ExpenseFiscalClassification::count());
        $expense->taxLines()->update(['deductible_amount' => 10.5]);
        $this->post(route('tax.expenses.classification.update', $expense), $this->payload($expense))->assertSessionHasNoErrors();
        $expense->update(['paid_amount' => 10, 'status' => 'partial']);
        $this->assertTrue(app(ExpenseFiscalReview::class)->isCurrent($expense->fresh()));
        $expense->taxLines()->update(['tax_rate' => 10]);
        $this->assertFalse(app(ExpenseFiscalReview::class)->isCurrent($expense->fresh()));
    }

    public function test_protects_closed_fiscal_year_accounting_period_and_prepared_return(): void
    {
        $expense = $this->expense();
        $year = FiscalYear::where('year', 2026)->firstOrFail();
        $year->update(['status' => 'closed']);
        $this->post(route('tax.expenses.classification.update', $expense), $this->payload($expense))->assertSessionHasErrors('operation_class');
        $year->update(['status' => 'open']);
        AccountingPeriod::updateOrCreate(['year' => 2026, 'month' => 9], ['fiscal_year_id' => $year->id, 'status' => 'closed']);
        $this->post(route('tax.expenses.classification.update', $expense), $this->payload($expense))->assertSessionHasErrors('accounting_period');
        AccountingPeriod::where('year', 2026)->where('month', 9)->update(['status' => 'open']);
        TaxReturn::create(['tax_type' => 'vat', 'year' => 2026, 'quarter' => 3, 'period_from' => '2026-07-01', 'period_to' => '2026-09-30',
            'status' => 'prepared', 'result' => 0, 'output_vat' => 0, 'input_vat' => 0, 'snapshot' => [], 'prepared_at' => now()]);
        $this->post(route('tax.expenses.classification.update', $expense), $this->payload($expense))->assertSessionHasErrors('operation_class');
        $this->assertSame(0, ExpenseFiscalClassification::count());
        $this->get(route('tax.expenses.classification.show', $expense))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canReview', false));
    }

    public function test_later_deduction_preserves_invoice_date_and_partial_documentary_quota(): void
    {
        $expense = $this->expense();
        $expense->update(['expense_date' => '2026-06-30']);
        $payload = [...$this->payload($expense), 'registered_on' => '2026-07-01'];
        $this->post(route('tax.expenses.classification.update', $expense), $payload)->assertSessionHasNoErrors();
        $this->get('/tax/models/303?year=2026&quarter=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('preview.deferredReceived', 1)->where('preview.deductions.0.base', null));
        $this->get('/tax/models/303?year=2026&quarter=3')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('preview.deductions.0.base', 100)->where('preview.deductions.0.vat', 10.5)->has('preview.pendingReceived', 0));
        $this->assertSame('2026-06-30', $expense->fresh()->expense_date->format('Y-m-d'));
        $expense->update(['document_number' => 'CORRECTED']);
        $this->get('/tax/models/303?year=2026&quarter=3')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('preview.pendingReceived', 1)->where('preview.deductions.0.base', null));
    }

    public function test_rejects_invalid_or_missing_explicit_periods(): void
    {
        $expense = $this->expense();
        foreach ([['registered_on' => '2026-09-14'], ['registered_on' => null], ['deduction_year' => 2027], ['deduction_quarter' => 2], ['registered_on' => '2026-12-31']] as $invalid) {
            $this->post(route('tax.expenses.classification.update', $expense), [...$this->payload($expense), ...$invalid])->assertSessionHasErrors();
        }
        $this->post(route('tax.expenses.classification.update', $expense), [...$this->payload($expense), 'source_reference' => ''])->assertSessionHasErrors('source_reference');
        $this->assertSame(0, ExpenseFiscalClassification::count());
    }

    public function test_previously_assigned_protected_period_blocks_reassignment_and_withdrawal(): void
    {
        $expense = $this->expense();
        $expense->update(['expense_date' => '2026-06-30']);
        $this->post(route('tax.expenses.classification.update', $expense), [...$this->payload($expense), 'registered_on' => '2026-07-01'])->assertSessionHasNoErrors();
        TaxReturn::create(['tax_type' => 'vat', 'year' => 2026, 'quarter' => 3, 'period_from' => '2026-07-01', 'period_to' => '2026-09-30',
            'status' => 'prepared', 'result' => 0, 'output_vat' => 0, 'input_vat' => 0, 'snapshot' => [], 'prepared_at' => now()]);
        $this->post(route('tax.expenses.classification.update', $expense), [...$this->payload($expense), 'deduction_quarter' => 4])->assertSessionHasErrors('operation_class');
        $this->post(route('tax.expenses.classification.update', $expense), $this->payload($expense, 'pending'))->assertSessionHasErrors('operation_class');
        $this->assertSame(3, $expense->fresh()->fiscalClassification->deduction_quarter);
        $this->assertSame(1, AuditLog::where('event', 'expense_fiscal_classified')->count());
    }

    public function test_legacy_review_without_explicit_period_requires_reconfirmation(): void
    {
        $expense = $this->expense();
        $this->post(route('tax.expenses.classification.update', $expense), $this->payload($expense))->assertSessionHasNoErrors();
        $expense->fiscalClassification()->update(['registered_on' => null, 'deduction_year' => null, 'deduction_quarter' => null]);
        $this->assertFalse(app(ExpenseFiscalReview::class)->isCurrent($expense->fresh()));
        $this->get('/tax/models/303?year=2026&quarter=3')->assertOk()->assertInertia(fn (Assert $page) => $page->has('preview.pendingReceived', 1));
    }

    public function test_only_admin_can_access_and_class_must_be_selected_from_defined_options(): void
    {
        $expense = $this->expense();
        $this->post(route('tax.expenses.classification.update', $expense), [...$this->payload($expense), 'operation_class' => 'invented'])->assertSessionHasErrors('operation_class');
        $payload = $this->payload($expense);
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        $this->get(route('tax.expenses.classification.show', $expense))->assertForbidden();
        $this->post(route('tax.expenses.classification.update', $expense), $payload)->assertForbidden();
        $this->assertSame(0, ExpenseFiscalClassification::count());
    }
}
