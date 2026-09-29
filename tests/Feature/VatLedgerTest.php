<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\SalesDocument;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Tax\VatLedger;
use App\Services\Tax\VatReport;
use App\Services\Tax\VatSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VatLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $code, ?string $parent = '472', string $type = 'asset'): LedgerAccount
    {
        return LedgerAccount::create(['code' => $code, 'name' => $code, 'parent_code' => $parent, 'type' => $type, 'is_active' => true, 'is_postable' => true]);
    }

    private function movement(LedgerAccount $account, float $debit, float $credit = 0, string $kind = 'automatic', string $status = 'posted', string $date = '2026-09-15'): void
    {
        $entry = JournalEntry::create(['entry_date' => $date, 'number' => 'TEST-'.(JournalEntry::count() + 1), 'description' => 'VAT test', 'entry_kind' => $kind, 'status' => $status]);
        $entry->lines()->create(['ledger_account_id' => $account->id, 'debit' => $debit, 'credit' => $credit]);
    }

    private function ledger(): array
    {
        return app(VatLedger::class)->make(Carbon::parse('2026-07-01'), Carbon::parse('2026-09-30')->endOfDay());
    }

    public function test_hierarchy_and_inactive_history_are_included_without_prefix_guessing(): void
    {
        $parent = $this->account('47210');
        $child = $this->account('47210001', '47210');
        $child->update(['is_active' => false]);
        $unrelated = $this->account('472999', '430');
        $this->movement($parent, 10);
        $this->movement($child, 21);
        $this->movement($child, 0, 1, 'expense_correction');
        $this->movement($unrelated, 900);
        $this->assertEquals(30, $this->ledger()['input']['total']);
        $this->assertCount(2, $this->ledger()['input']['accounts']);
    }

    public function test_explicit_mapping_is_included_once_and_cycles_terminate(): void
    {
        $account = $this->account('VAT-CUSTOM', 'VAT-CUSTOM');
        TaxRate::where('kind', 'vat')->update(['input_account_code' => $account->code, 'is_active' => false]);
        $this->movement($account, 12);
        $this->assertEquals(12, $this->ledger()['input']['total']);
        $this->assertCount(1, $this->ledger()['input']['accounts']);
    }

    public function test_drafts_settlements_opening_closing_and_outside_dates_are_excluded(): void
    {
        $account = $this->account('47721', '477', 'liability');
        $this->movement($account, 0, 21);
        $this->movement($account, 1, 0, 'reversal');
        foreach (['opening', 'closing', 'opening_reversal', 'closing_reversal', 'tax_settlement'] as $kind) {
            $this->movement($account, 0, 100, $kind);
        }
        $this->movement($account, 0, 100, 'manual', 'draft');
        $this->movement($account, 0, 100, 'automatic', 'posted', '2026-06-30');
        $before = JournalEntry::count();
        $this->assertEquals(20, $this->ledger()['output']['total']);
        $this->assertSame($before, JournalEntry::count());
    }

    public function test_invalid_account_mapping_requires_review(): void
    {
        TaxRate::where('kind', 'vat')->update(['input_account_code' => '700']);
        $this->expectException(ValidationException::class);
        $this->ledger();
    }

    public function test_settlement_closes_the_actual_subaccount_and_preserves_breakdown(): void
    {
        $account = $this->account('47200021');
        TaxRate::where('kind', 'vat')->where('rate', 21)->update(['input_account_code' => $account->code]);
        $user = User::factory()->create(['role' => 'admin']);
        $expense = Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'created_by' => $user->id,
            'number' => 'EXP-VAT-SUB', 'type' => 'invoice', 'expense_date' => '2026-09-15', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'status' => 'pending']);
        app(AccountingSynchronizer::class)->syncExpense($expense, $user->id);
        $this->assertTrue(app(VatReport::class)->make(2026, 3)['accountingCheck']['matches']);
        $return = app(VatSettlement::class)->prepare(2026, 3, $user->id);
        $this->assertSame('47200021', $return->snapshot['vat_accounts']['input']['accounts'][0]['account_code']);
        $entry = JournalEntry::with('lines.account')->findOrFail($return->journal_entry_id);
        $this->assertEquals(21, $entry->lines->firstWhere('account.code', '47200021')->credit);
        $this->assertNull($entry->lines->firstWhere('account.code', '472'));
        $this->assertTrue(app(VatReport::class)->make(2026, 3)['accountingCheck']['matches']);
    }

    public function test_negative_output_vat_reverses_the_settlement_side(): void
    {
        $account = $this->account('47700021', '477', 'liability');
        TaxRate::where('kind', 'vat')->where('rate', 21)->update(['output_account_code' => $account->code]);
        $user = User::factory()->create(['role' => 'admin']);
        $credit = SalesDocument::create(['type' => 'credit_note', 'number' => 'CR-VAT-SUB', 'status' => 'issued',
            'issue_date' => '2026-09-15', 'issued_at' => now(), 'locked_at' => now(),
            'subtotal' => -100, 'discount_amount' => 0, 'tax_amount' => -21, 'amount' => -121, 'paid_amount' => 0, 'customer_snapshot' => ['company_name' => 'VAT test customer']]);
        $credit->lines()->create(['position' => 1, 'description' => 'Credit', 'quantity' => -1,
            'unit_price' => 100, 'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => -100,
            'discount_amount' => 0, 'tax_amount' => -21, 'total' => -121]);
        $return = app(VatSettlement::class)->prepare(2026, 3, $user->id);
        $entry = JournalEntry::with('lines.account')->findOrFail($return->journal_entry_id);
        $this->assertEquals(-21, $return->result);
        $this->assertEquals(21, $entry->lines->firstWhere('account.code', '47700021')->credit);
        $this->assertEquals($entry->lines->sum('debit'), $entry->lines->sum('credit'));
    }
}
