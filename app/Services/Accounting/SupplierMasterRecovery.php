<?php

namespace App\Services\Accounting;

use App\Models\Expense;
use App\Models\FiscalEntity;
use App\Models\Supplier;

class SupplierMasterRecovery
{
    /** No document links, payments, entries or historical classifications are changed. */
    public function run(bool $apply = false): array
    {
        $identities = Expense::where('type', 'invoice')->where('currency_code', 'EUR')
            ->whereNotNull('issuer_tax_id')->whereNotNull('issuer_legal_name')
            ->get(['type', 'currency_code', 'issuer_country', 'issuer_tax_id', 'issuer_legal_name'])
            ->filter(fn ($expense) => filled($expense->issuer_tax_id) && filled($expense->issuer_legal_name))
            ->groupBy(fn ($expense) => ($expense->issuer_country ?: 'ES').':'.FiscalEntity::taxKey($expense->issuer_tax_id));
        $ambiguous = 0;
        $processed = 0;
        foreach ($identities as $expenses) {
            if ($expenses->map(fn ($expense) => mb_strtoupper(trim($expense->issuer_legal_name)))->unique()->count() > 1) {
                $ambiguous++;

                continue;
            }
            if ($apply) {
                $data = $expenses->first()->toArray();
                $data['issuer_country'] = $data['issuer_country'] ?: 'ES';
                app(ExpenseCreditorRegistry::class)->supplier($data);
            }
            $processed++;
        }
        if ($apply) {
            Supplier::orderBy('id')->each(fn ($supplier) => app(SupplierLedgerAccountManager::class)->sync($supplier));
        }

        return ['eligible_identities' => $processed, 'ambiguous_identities_skipped' => $ambiguous, 'applied' => $apply];
    }
}
