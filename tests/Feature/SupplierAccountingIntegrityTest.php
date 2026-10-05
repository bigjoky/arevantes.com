<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\ExpenseCreditorRegistry;
use App\Services\Accounting\JournalPoster;
use App\Services\Accounting\SupplierLedgerAccountManager;
use App\Services\Accounting\SupplierMasterRecovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierAccountingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        return ['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => 'invoice',
            'currency_code' => 'EUR', 'issuer_legal_name' => 'Supplier SA', 'issuer_tax_id' => 'A12345678',
            'document_number' => 'SUP-1', 'expense_date' => '2026-09-15', 'subtotal' => 100, 'tax_amount' => 21];
    }

    public function test_two_suppliers_with_same_nif_suffix_do_not_share_accounts(): void
    {
        $a = Supplier::create(['legal_name' => 'One', 'tax_id' => 'A12345678', 'country' => 'ES']);
        $b = Supplier::create(['legal_name' => 'Two', 'tax_id' => 'B54321678', 'country' => 'ES']);
        foreach ([$a, $b] as $supplier) {
            $this->assertStringEndsWith('678', $supplier->payable_account_code);
            $this->assertStringEndsWith('678', $supplier->service_payable_account_code);
            $this->assertStringStartsWith('4000', $supplier->payable_account_code);
            $this->assertStringStartsWith('4100', $supplier->service_payable_account_code);
        }
        $this->assertNotSame($a->payable_account_code, $b->payable_account_code);
        $this->assertNotSame($a->service_payable_account_code, $b->service_payable_account_code);
        $code = $a->payable_account_code;
        $a->update(['tax_id' => 'A99999999']);
        $this->assertSame($code, $a->fresh()->payable_account_code);
    }

    public function test_new_expense_automatically_registers_supplier_and_payment_uses_same_creditor(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->post(route('expenses.store'), $this->data())->assertRedirect()->assertSessionHasNoErrors();
        $expense = Expense::sole();
        $this->assertNotNull($expense->supplier_id);
        $this->assertStringStartsWith('4100', $expense->creditor_account_code);
        $this->assertSame($expense->supplier->service_payable_account_code, $expense->creditor_account_code);
        $this->assertTrue($expense->accountingEntry->lines()->whereHas('account', fn ($query) => $query->where('code', $expense->creditor_account_code))->exists());
        $payment = SupplierPayment::create(['expense_id' => $expense->id, 'created_by' => $user->id, 'amount' => 121, 'method' => 'cash', 'paid_at' => '2026-09-15']);
        app(AccountingSynchronizer::class)->syncSupplierPayment($payment);
        $balance = LedgerAccount::where('code', $expense->creditor_account_code)->firstOrFail()->lines()->get();
        $this->assertEquals($balance->sum('debit'), $balance->sum('credit'));
        $this->assertSame(0, app(AccountingSynchronizer::class)->sync());
    }

    public function test_normalized_identity_reuses_supplier_without_overwriting_its_legal_name(): void
    {
        $supplier = Supplier::create(['legal_name' => 'Reviewed master name', 'tax_id' => 'A-12345678', 'country' => 'ES', 'is_active' => true]);
        $one = app(ExpenseCreditorRegistry::class)->newInvoice($this->data());
        $two = app(ExpenseCreditorRegistry::class)->newInvoice($this->data());
        $this->assertSame($supplier->id, $one['supplier_id']);
        $this->assertSame($one['creditor_account_code'], $two['creditor_account_code']);
        $this->assertSame('Reviewed master name', $supplier->fresh()->legal_name);
        $this->assertSame(1, Supplier::count());
    }

    public function test_master_recovery_is_dry_by_default_and_does_not_touch_historical_documents(): void
    {
        $expense = Expense::create([...$this->data(), 'number' => 'EXP-OLD', 'amount' => 121, 'status' => 'pending']);
        $before = $expense->fresh()->getAttributes();
        $recovery = app(SupplierMasterRecovery::class);
        $this->assertSame(1, $recovery->run()['eligible_identities']);
        $this->assertSame(0, Supplier::count());
        $recovery->run(true);
        $recovery->run(true);
        $this->assertSame(1, Supplier::count());
        $this->assertSame($before, $expense->fresh()->getAttributes());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_automatic_posting_rejects_inactive_accounts_and_missing_year_and_counts_posts(): void
    {
        $expense = Expense::create([...$this->data(), 'number' => 'EXP-SYNC', 'amount' => 121, 'status' => 'pending']);
        $synchronizer = app(AccountingSynchronizer::class);
        $account = LedgerAccount::where('code', '629')->firstOrFail();
        $account->update(['is_active' => false]);
        try {
            $synchronizer->syncExpense($expense);
            $this->fail('Inactive account accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines', $exception->errors());
        }
        $this->assertSame(0, JournalEntry::count());
        $account->update(['is_active' => true]);
        $year = FiscalYear::where('year', 2026)->firstOrFail();
        $year->update(['starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
        try {
            $synchronizer->syncExpense($expense);
            $this->fail('Missing fiscal year accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('entry_date', $exception->errors());
        }
        $year->update(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->assertSame(1, $synchronizer->sync());
        $this->assertSame(0, $synchronizer->sync());
    }

    public function test_inactive_supplier_account_is_not_reactivated_by_renaming_supplier(): void
    {
        $supplier = Supplier::create(['legal_name' => 'Supplier', 'tax_id' => 'A12345678', 'country' => 'ES']);
        LedgerAccount::where('code', $supplier->payable_account_code)->update(['is_active' => false]);
        $supplier->update(['legal_name' => 'New name']);
        app(SupplierLedgerAccountManager::class)->sync($supplier);
        $this->assertFalse(LedgerAccount::where('code', $supplier->payable_account_code)->firstOrFail()->is_active);
    }

    public function test_supplier_subaccounts_cannot_be_deleted_and_used_accounts_cannot_change_nature(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $supplier = Supplier::create(['legal_name' => 'Supplier', 'tax_id' => 'A12345678', 'country' => 'ES']);
        $account = LedgerAccount::where('code', $supplier->payable_account_code)->firstOrFail();
        $this->actingAs($user)->delete(route('accounting.accounts.destroy', $account))->assertSessionHasErrors('account');
        app(JournalPoster::class)->post('2026-09-15', 'Test', [
            ['account_code' => $account->code, 'debit' => 0, 'credit' => 100], ['account_code' => '629', 'debit' => 100, 'credit' => 0]], $user->id);
        $this->patch(route('accounting.accounts.update', $account), ['name' => $account->name, 'type' => 'income',
            'parent_code' => $account->parent_code, 'is_active' => true, 'is_postable' => true])->assertSessionHasErrors('account');
        $this->assertSame('liability', $account->fresh()->type);
    }

    public function test_persisted_creditor_assignments_cannot_be_removed_by_migration_rollback(): void
    {
        Supplier::create(['legal_name' => 'Supplier', 'tax_id' => 'A12345678', 'country' => 'ES']);
        $migration = require database_path('migrations/2026_10_05_050000_add_supplier_creditor_accounts.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot remove persisted creditor account assignments.');
        $migration->down();
    }

    public function test_negative_journal_line_is_rejected_before_any_posting(): void
    {
        $this->expectException(ValidationException::class);
        app(JournalPoster::class)->post('2026-09-15', 'Invalid', [
            ['account_code' => '629', 'debit' => -10, 'credit' => 0], ['account_code' => '410', 'debit' => 0, 'credit' => -10]], null);
    }
}
