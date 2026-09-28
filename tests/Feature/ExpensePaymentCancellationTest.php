<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\PermissionProfile;
use App\Models\SupplierPayment;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpensePaymentCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function payment(): array
    {
        $user = User::factory()->create(['role' => 'admin']);
        $expense = Expense::create(['number' => 'EXP-VOID-1', 'expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => 'receipt', 'merchant_name' => 'Test supplier', 'expense_date' => '2026-09-20', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 121, 'status' => 'paid']);
        $payment = $expense->payments()->create(['created_by' => $user->id, 'paid_at' => '2026-09-21', 'amount' => 121, 'method' => 'cash', 'reference' => 'Original reference']);
        app(AccountingSynchronizer::class)->syncSupplierPayment($payment, $user->id);
        $this->actingAs($user);

        return [$expense, $payment, $user];
    }

    public function test_cancellation_preserves_original_and_reverses_accounting_exactly_once(): void
    {
        [$expense, $payment, $user] = $this->payment();
        $original = $payment->accountingEntry;
        $this->post(route('expenses.payments.cancel', [$expense, $payment]), ['reason' => 'Incorrect payment date'])->assertRedirect()->assertSessionHasNoErrors();
        $retained = SupplierPayment::withoutGlobalScope('active_supplier_payment')->findOrFail($payment->id);
        $this->assertSame('121.00', $retained->amount);
        $this->assertSame('2026-09-21', $retained->paid_at->toDateString());
        $this->assertSame($user->id, $retained->voided_by);
        $this->assertNotNull($retained->voided_at);
        $this->assertSame('0.00', $expense->fresh()->paid_amount);
        $this->assertSame(0, SupplierPayment::count());
        $this->assertSame(0.0, (float) $expense->payments()->sum('amount'));
        $reversal = JournalEntry::findOrFail($retained->reversal_entry_id);
        $this->assertSame($original->id, $reversal->reverses_entry_id);
        $this->assertSame($payment->id, $original->fresh()->source->id);
        foreach ($original->lines as $line) {
            $opposite = $reversal->lines->firstWhere('ledger_account_id', $line->ledger_account_id);
            $this->assertSame($line->debit, $opposite->credit);
            $this->assertSame($line->credit, $opposite->debit);
        }
        $this->assertDatabaseHas('audit_logs', ['event' => 'expense_payment_voided', 'user_id' => $user->id, 'auditable_id' => $expense->id]);
        $this->get(route('expenses.show', $expense))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canCancelPayments', true)->has('expense.payments', 1)->where('expense.payments.0.void_reason', 'Incorrect payment date')->where('expense.payments.0.voided_by.name', $user->name));
        $this->delete(route('expenses.destroy', $expense))->assertStatus(422);
        $this->post(route('expenses.payments.cancel', [$expense, $payment]), ['reason' => 'Duplicate submission'])->assertNotFound();
        $this->assertSame(2, JournalEntry::count());
        $this->post(route('expenses.payments.store', $expense), [
            'paid_at' => '2026-09-22', 'amount' => 121, 'method' => 'cash',
            'funding_source_type' => 'ledger_account', 'funding_source_id' => LedgerAccount::where('code', '570')->firstOrFail()->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('paid', $expense->fresh()->status);
        $this->assertSame('121.00', $expense->fresh()->paid_amount);
        $this->assertSame(1, SupplierPayment::count());
        $this->assertDatabaseCount('supplier_payments', 2);
    }

    public function test_only_administrators_may_cancel_and_a_reason_is_required(): void
    {
        [$expense, $payment] = $this->payment();
        $this->post(route('expenses.payments.cancel', [$expense, $payment]), ['reason' => ' '])->assertSessionHasErrors('reason');
        $finance = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => PermissionProfile::where('slug', 'finance')->firstOrFail()->id]);
        $this->assertTrue($finance->can('manage-purchase-payments'));
        $this->actingAs($finance);
        $this->get(route('expenses.show', $expense))->assertInertia(fn (Assert $page) => $page->where('canCancelPayments', false));
        $this->post(route('expenses.payments.cancel', [$expense, $payment]), ['reason' => 'Incorrect date'])->assertForbidden();
        $this->assertSame(1, SupplierPayment::count());
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_closed_period_blocks_cancellation_without_side_effects(): void
    {
        [$expense, $payment] = $this->payment();
        AccountingPeriod::create(['fiscal_year_id' => FiscalYear::where('year', 2026)->firstOrFail()->id, 'year' => 2026, 'month' => 9, 'status' => 'closed']);
        $this->post(route('expenses.payments.cancel', [$expense, $payment]), ['reason' => 'Incorrect date'])->assertSessionHasErrors('accounting_period');
        $this->assertSame('121.00', $expense->fresh()->paid_amount);
        $this->assertSame(1, SupplierPayment::count());
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_a_prepared_tax_return_protects_payment_history(): void
    {
        [$expense, $payment] = $this->payment();
        $return = TaxReturn::create(['tax_type' => 'vat', 'year' => 2026, 'quarter' => 3, 'period_from' => '2026-07-01', 'period_to' => '2026-09-30', 'status' => 'prepared', 'output_vat' => 0, 'input_vat' => 21, 'result' => -21, 'snapshot' => [], 'prepared_at' => now()]);
        $this->post(route('expenses.payments.cancel', [$expense, $payment]), ['reason' => 'Incorrect date'])->assertSessionHasErrors('reason');
        $return->update(['status' => 'filed']);
        $this->post(route('expenses.payments.cancel', [$expense, $payment]), ['reason' => 'Incorrect date'])->assertSessionHasErrors('reason');
        $this->assertSame(1, SupplierPayment::count());
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_reconciled_payment_cannot_be_cancelled_from_expenses(): void
    {
        [$expense, $payment, $user] = $this->payment();
        $bank = BankAccount::create(['name' => 'Test bank', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $transaction = $bank->transactions()->create(['booked_on' => '2026-09-21', 'amount' => -121, 'currency' => 'EUR', 'description' => 'Test payment', 'import_hash' => hash('sha256', 'cancel-guard'), 'status' => 'reconciled']);
        BankReconciliation::create(['bank_transaction_id' => $transaction->id, 'supplier_payment_id' => $payment->id, 'reconciled_by' => $user->id, 'method' => 'manual', 'reconciled_at' => now()]);
        $this->post(route('expenses.payments.cancel', [$expense, $payment]), ['reason' => 'Incorrect date'])->assertSessionHasErrors('reason');
        $this->assertSame(1, SupplierPayment::count());
        $this->assertSame('reconciled', $transaction->fresh()->status);
    }

    public function test_closed_fiscal_year_blocks_cancellation(): void
    {
        [$expense, $payment] = $this->payment();
        FiscalYear::where('year', 2026)->update(['status' => 'closed']);
        $this->post(route('expenses.payments.cancel', [$expense, $payment]), ['reason' => 'Incorrect date'])->assertSessionHasErrors('reason');
        $this->assertSame(1, SupplierPayment::count());
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_other_expense_payment_cannot_be_cancelled(): void
    {
        [$expense, $payment] = $this->payment();
        $other = $expense->replicate();
        $other->number = 'EXP-OTHER';
        $other->save();
        $this->post(route('expenses.payments.cancel', [$other, $payment]), ['reason' => 'Incorrect date'])->assertNotFound();
        $this->assertSame(1, SupplierPayment::count());
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_audit_failure_rolls_back_payment_balance_and_reversal(): void
    {
        [$expense, $payment] = $this->payment();
        Event::listen('eloquent.creating: '.AuditLog::class, function () {
            throw new \RuntimeException('Audit unavailable');
        });
        try {
            $this->post(route('expenses.payments.cancel', [$expense, $payment]), ['reason' => 'Incorrect date'])->assertStatus(500);
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }
        $this->assertSame(1, SupplierPayment::count());
        $this->assertSame(1, JournalEntry::count());
        $this->assertSame('121.00', $expense->fresh()->paid_amount);
    }
}
