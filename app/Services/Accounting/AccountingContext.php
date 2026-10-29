<?php

namespace App\Services\Accounting;

use App\Models\LedgerAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AccountingContext
{
    public function forRequest(Request $r): ?array
    {
        if (! $r->user()?->can('view-finance')) {
            return null;
        }
        foreach (['person' => 'customer', 'supplier' => 'supplier', 'product' => 'product', 'salesDocument' => 'sale', 'purchaseDocument' => 'purchase', 'expense' => 'expense'] as $parameter => $kind) {
            $value = $r->route($parameter);
            if (! $value) {
                continue;
            }
            $m = $value instanceof Model ? $value : AccountingAccountPolicy::ENTITIES[$kind]::find($value);
            if (! $m) {
                return null;
            }
            $policy = app(AccountingAccountPolicy::class);
            $rows = [];
            $legacy = ['customer' => ['receivable' => 'receivable_account_code'], 'supplier' => ['supplier' => 'payable_account_code', 'creditor' => 'service_payable_account_code'], 'product' => ['revenue' => 'sale_account_code', 'purchase' => 'purchase_account_code'], 'expense' => ['creditor' => 'creditor_account_code']][$kind] ?? [];
            foreach (AccountingAccountPolicy::ENTITY_ROLES[$kind] as $role) {
                $field = $legacy[$role] ?? null;
                $code = $m->getAttribute('accounting_overrides')[$role] ?? ($policy->assigned($m, $role) ?: ($field ? $m->$field : null));
                $rows[] = ['role' => $role, 'code' => $code, 'name' => $code ? LedgerAccount::where('code', $code)->value('name') : null, 'origin' => $code ? 'specific' : 'unregistered'];
            }

            return ['kind' => $kind, 'id' => $m->id, 'href' => '/accounting/control?entity='.$kind.'&entity_id='.$m->id, 'rows' => $rows];
        }

        return null;
    }
}
