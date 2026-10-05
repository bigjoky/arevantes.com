<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\SupplierPayment;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Accounting\ExpenseCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function expense(): Expense
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('expenses.store'), ['expense_category_id' => ExpenseCategory::firstOrFail()->id,
            'type' => 'invoice', 'document_number' => 'COR-1', 'issuer_legal_name' => 'Supplier', 'issuer_tax_id' => 'A12345678',
            'expense_date' => '2026-09-15', 'subtotal' => 100, 'tax_amount' => 21])->assertSessionHasNoErrors();

        return Expense::firstOrFail();
    }

    private function payload(Expense $expense): array
    {
        return [...$expense->only($expense->getFillable()), 'expense_date' => $expense->expense_date->toDateString(),
            'revision' => ExpenseCorrection::revision($expense), 'correction_reason' => 'Incorrect category selected',
            'tax_lines' => $expense->taxLines->map->only(['kind', 'taxable_base', 'tax_rate', 'tax_amount', 'deductible_percent', 'exemption_reason'])->all()];
    }

    public function test_admin_can_change_category_and_preserve_original_posting(): void
    {
        $expense = $this->expense();
        $original = $expense->accountingEntry->load('lines')->toArray();
        $category = ExpenseCategory::create(['code' => 'correction-test', 'name' => 'Correction test', 'expense_account_code' => '628', 'is_active' => true]);
        $this->get(route('expenses.edit', $expense))->assertInertia(fn (Assert $page) => $page->component('expenses/edit')->where('expense.expense_category_id', $expense->expense_category_id));
        $payload = [...$this->payload($expense), 'expense_category_id' => $category->id];
        $this->patch(route('expenses.update', $expense), $payload)->assertSessionHasNoErrors()->assertRedirect(route('expenses.show', $expense));
        $this->assertEquals($category->id, $expense->fresh()->expense_category_id);
        $this->assertSame($original, $expense->accountingEntry->fresh()->load('lines')->toArray());
        $this->assertSame(3, JournalEntry::count());
        $this->assertSame(1, AuditLog::where('event', 'expense_corrected')->count());
        $this->patch(route('expenses.update', $expense), $payload)->assertSessionHasErrors('correction_reason');
        $this->assertSame(3, JournalEntry::count());
    }

    public function test_amount_date_and_metadata_can_be_corrected_repeatedly(): void
    {
        $expense = $this->expense();
        foreach ([200, 300] as $base) {
            $this->patch(route('expenses.update', $expense), [...$this->payload($expense->refresh()),
                'expense_date' => '2026-05-29', 'document_number' => 'REVISED', 'issuer_legal_name' => 'Correct supplier',
                'tax_lines' => [['kind' => 'vat', 'taxable_base' => $base, 'tax_rate' => 21, 'tax_amount' => $base * .21, 'deductible_percent' => 100]],
            ])->assertSessionHasNoErrors();
        }
        $expense->refresh();
        $this->assertSame('363.00', $expense->amount);
        $this->assertSame('2026-05-29', $expense->expense_date->toDateString());
        $this->assertSame('REVISED', $expense->document_number);
        $this->assertSame(5, JournalEntry::count());
        $this->assertSame(2, AuditLog::where('event', 'expense_corrected')->count());
        $netCreditor = JournalLine::whereHas('account', fn ($query) => $query->where('code', $expense->creditor_account_code))->get()->sum(fn ($line) => (float) $line->credit - (float) $line->debit);
        $this->assertEquals(363, $netCreditor);
        foreach (JournalEntry::with('lines')->get() as $entry) {
            $this->assertEquals($entry->lines->sum('debit'), $entry->lines->sum('credit'));
        }
    }

    public function test_paid_expenses_and_non_admin_users_are_blocked(): void
    {
        $expense = $this->expense();
        $payload = $this->payload($expense);
        $expense->update(['status' => 'paid', 'paid_amount' => 121]);
        $this->patch(route('expenses.update', $expense), $payload)->assertSessionHasErrors('correction_reason');
        $this->get(route('expenses.edit', $expense))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        $this->patch(route('expenses.update', $expense), $payload)->assertForbidden();
    }

    public function test_closed_year_and_prepared_return_block_changes(): void
    {
        $expense = $this->expense();
        FiscalYear::where('year', 2026)->update(['status' => 'closed']);
        $this->patch(route('expenses.update', $expense), $this->payload($expense))->assertSessionHasErrors('correction_reason');
        FiscalYear::where('year', 2026)->update(['status' => 'open']);
        TaxReturn::create(['tax_type' => 'vat', 'year' => 2026, 'quarter' => 3, 'period_from' => '2026-07-01', 'period_to' => '2026-09-30', 'status' => 'prepared', 'output_vat' => 0, 'input_vat' => 21, 'result' => -21, 'snapshot' => [], 'prepared_at' => now()]);
        $this->patch(route('expenses.update', $expense), $this->payload($expense))->assertSessionHasErrors('correction_reason');
        $this->assertSame(0, AuditLog::where('event', 'expense_corrected')->count());
    }

    public function test_partial_payment_cannot_be_overpaid_by_editing(): void
    {
        $expense = $this->expense();
        SupplierPayment::create(['expense_id' => $expense->id, 'created_by' => auth()->id(), 'amount' => 100, 'method' => 'cash', 'paid_at' => '2026-09-15']);
        $expense->update(['paid_amount' => 100, 'status' => 'partial']);
        $this->patch(route('expenses.update', $expense), [...$this->payload($expense),
            'tax_lines' => [['kind' => 'vat', 'taxable_base' => 10, 'tax_rate' => 21, 'tax_amount' => 2.1, 'deductible_percent' => 100]],
        ])->assertSessionHasErrors('correction_reason');
        $this->assertSame('121.00', $expense->fresh()->amount);
    }

    public function test_failed_audit_rolls_back_document_tax_lines_and_correction_entries(): void
    {
        $expense = $this->expense();
        $before = ExpenseCorrection::snapshot($expense);
        AuditLog::creating(function ($log) {
            if ($log->event === 'expense_corrected') {
                throw new \RuntimeException('Audit unavailable');
            }
        });
        try {
            $this->patch(route('expenses.update', $expense), [...$this->payload($expense), 'expense_date' => '2026-05-29'])->assertStatus(500);
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertSame($before, ExpenseCorrection::snapshot($expense->fresh()));
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_partial_expense_can_change_notes_but_not_supplier_or_currency(): void
    {
        $expense = $this->expense();
        SupplierPayment::create(['expense_id' => $expense->id, 'created_by' => auth()->id(), 'amount' => 10, 'method' => 'cash', 'paid_at' => '2026-09-15']);
        $expense->update(['paid_amount' => 10, 'status' => 'partial']);
        $this->patch(route('expenses.update', $expense), [...$this->payload($expense), 'notes' => 'Corrected notes'])->assertSessionHasNoErrors();
        $this->assertSame('Corrected notes', $expense->fresh()->notes);
        $this->assertSame('partial', $expense->fresh()->status);
        $this->patch(route('expenses.update', $expense), [...$this->payload($expense->refresh()), 'currency_code' => 'USD'])->assertSessionHasErrors('correction_reason');
    }
}
