<?php

namespace App\Observers;

use App\Models\Person;
use App\Models\Supplier;
use App\Services\Accounting\AccountingAccountPolicy;
use App\Services\Accounting\SupplierLedgerAccountManager;

class PartyLedgerObserver
{
    public function saved(Person|Supplier $party): void
    {
        if ($party instanceof Supplier) {
            if ($party->wasChanged(['payable_account_code', 'service_payable_account_code'])) {
                app(SupplierLedgerAccountManager::class)->sync($party);
            }

            return;
        }
        if ($party instanceof Person && $party->category === 'staff') {
            // Employee assignments are reviewed from the accounting panel too.

            return;
        }
        if ($party instanceof Person && $party->category !== 'customer') {
            return;
        }
        // Accounting associations are explicit decisions. Saving identity data never provisions,
        // renames or reactivates a ledger account.
        if ($party->receivable_account_code && $party->wasChanged('receivable_account_code')) {
            app(AccountingAccountPolicy::class)->valid($party->receivable_account_code, 'receivable');
        }
    }
}
