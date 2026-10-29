<?php

namespace App\Services\Accounting;

use App\Models\Supplier;

/** Validate explicit associations without creating or silently repairing accounts. */
class SupplierLedgerAccountManager
{
    public function sync(Supplier $supplier): void
    {
        foreach (['payable_account_code' => 'supplier', 'service_payable_account_code' => 'creditor'] as $field => $role) {
            if ($supplier->$field) {
                app(AccountingAccountPolicy::class)->valid($supplier->$field, $role);
            }
        }
    }
}
