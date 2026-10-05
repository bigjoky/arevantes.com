<?php

namespace App\Services\Accounting;

use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Supplier;

/** Read-only checks; an empty exception list is not a PGC compliance certification. */
class AccountingHealthReport
{
    public function make(): array
    {
        $accounts = LedgerAccount::get()->keyBy('code');
        $suppliers = Supplier::get(['id', 'payable_account_code', 'service_payable_account_code']);
        $entries = JournalEntry::where('status', 'posted')->withSum('lines as debit_total', 'debit')->withSum('lines as credit_total', 'credit')->get();

        return [
            'pgc_accounts' => $accounts->where('source', 'pgc')->count(),
            'accounts_missing_parent' => $accounts->filter(fn ($account) => $account->parent_code && ! $accounts->has($account->parent_code))->count(),
            'suppliers' => $suppliers->count(),
            'suppliers_missing_goods_account' => $suppliers->filter(fn ($supplier) => ! $supplier->payable_account_code || ! $accounts->has($supplier->payable_account_code))->count(),
            'suppliers_missing_services_account' => $suppliers->filter(fn ($supplier) => ! $supplier->service_payable_account_code || ! $accounts->has($supplier->service_payable_account_code))->count(),
            'expenses_without_supplier' => Expense::whereNull('supplier_id')->count(),
            'legacy_expenses_without_frozen_account' => Expense::whereNull('creditor_account_code')->count(),
            'expenses_requiring_currency_review' => Expense::whereNot('currency_code', 'EUR')->count(),
            'posted_entries' => $entries->count(),
            'unbalanced_entries' => $entries->filter(fn ($entry) => (int) round((float) $entry->debit_total * 100) !== (int) round((float) $entry->credit_total * 100))->count(),
            'entries_without_fiscal_year' => $entries->whereNull('fiscal_year_id')->count(),
        ];
    }
}
