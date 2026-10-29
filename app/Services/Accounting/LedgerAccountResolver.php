<?php

namespace App\Services\Accounting;

use App\Models\LedgerAccount;
use Illuminate\Validation\ValidationException;

class LedgerAccountResolver
{
    public function resolveOrCreate(string $code, ?string $name, string $field): LedgerAccount
    {
        $code = trim($code);
        if (! preg_match('/^[0-9]{3,20}$/', $code)) {
            throw ValidationException::withMessages([$field => __('Use an accounting code containing between 3 and 20 digits.')]);
        }

        $existing = LedgerAccount::where('code', $code)->first();
        if ($existing) {
            if (! $existing->is_active || ! $existing->is_postable) {
                throw ValidationException::withMessages([$field => __('The selected accounting account is inactive or not postable.')]);
            }

            return $existing;
        }

        abort_unless(auth()->user()?->can('manage-ledger-accounts'), 403);
        if (blank($name)) {
            throw ValidationException::withMessages([str_replace('account_code', 'account_name', $field) => __('Enter a name to create this subaccount.')]);
        }

        $parent = LedgerAccount::where('is_active', true)
            ->get()
            ->filter(fn (LedgerAccount $account) => strlen($account->code) < strlen($code) && str_starts_with($code, $account->code))
            ->sortByDesc(fn (LedgerAccount $account) => strlen($account->code))
            ->first();

        if (! $parent) {
            throw ValidationException::withMessages([$field => __('The new subaccount must extend an existing account in the chart of accounts.')]);
        }

        return LedgerAccount::firstOrCreate(['code' => $code], [
            'name' => trim((string) $name),
            'type' => $parent->type,
            'parent_code' => $parent->code,
            'level' => strlen($code),
            'is_postable' => true,
            'is_active' => true,
            'source' => 'custom',
            'financial_category_id' => $parent->financial_category_id,
        ]);
    }
}
