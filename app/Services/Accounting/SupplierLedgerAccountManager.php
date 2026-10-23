<?php

namespace App\Services\Accounting;

use App\Models\AuditLog;
use App\Models\LedgerAccount;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SupplierLedgerAccountManager
{
    public function sync(Supplier $supplier): void
    {
        DB::transaction(function () use ($supplier) {
            $locked = Supplier::lockForUpdate()->findOrFail($supplier->id);
            $fields = ['payable_account_code' => '4000'];
            if (Schema::hasColumn('suppliers', 'service_payable_account_code')) {
                $fields['service_payable_account_code'] = '4100';
            }
            $changes = [];
            foreach ($fields as $field => $parent) {
                $code = $locked->$field;
                if ($code) {
                    // Never silently renumber, reactivate or appropriate an existing account.
                    $existing = LedgerAccount::where('code', $code)->first();
                    if (! $existing || ! str_starts_with($code, substr($parent, 0, 3)) || $existing->type !== 'liability') {
                        throw ValidationException::withMessages(['accounting' => __('Review the supplier accounting account before continuing.')]);
                    }

                    continue;
                }
                LedgerAccount::where('code', $parent)->lockForUpdate()->firstOrFail();
                $digits = preg_replace('/\D/', '', (string) $locked->tax_id) ?: (string) $locked->id;
                $suffix = str_pad(substr($digits, -3), 3, '0', STR_PAD_LEFT);
                for ($sequence = 0; $sequence < 100000; $sequence++) {
                    $code = $parent.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT).$suffix;
                    if (! LedgerAccount::where('code', $code)->exists()) {
                        break;
                    }
                }
                if ($sequence === 100000) {
                    throw new \RuntimeException('Supplier accounting subaccounts exhausted.');
                }
                LedgerAccount::create(['code' => $code, 'name' => $locked->legal_name, 'type' => 'liability',
                    'parent_code' => $parent, 'level' => strlen($code), 'is_active' => true, 'is_postable' => true, 'source' => 'custom']);
                $changes[$field] = $code;
            }
            if ($changes) {
                DB::table('suppliers')->where('id', $locked->id)->update($changes);
                AuditLog::create(['user_id' => auth()->id(), 'event' => 'supplier_accounts_provisioned',
                    'auditable_type' => $locked->getMorphClass(), 'auditable_id' => $locked->id,
                    'new_values' => $changes]);
            }
            foreach ($fields as $field => $parent) {
                $supplier->$field = $changes[$field] ?? $locked->$field;
            }
        });
    }
}
