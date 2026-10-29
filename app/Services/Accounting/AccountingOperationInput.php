<?php

namespace App\Services\Accounting;

use App\Models\FinancialCategory;
use App\Models\LedgerAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountingOperationInput
{
    public static function validate(Request $r, string $kind): array
    {
        if (! $r->has('accounting_overrides') && ! $r->has('accounting_category_id')) {
            return [];
        }
        abort_unless($r->user()?->can('assign-accounting-accounts'), 403);
        $d = $r->validate(['accounting_overrides' => 'nullable|array', 'accounting_overrides.*' => 'nullable|string|max:20', 'accounting_category_id' => 'nullable|integer|exists:financial_categories,id']);
        $result = [];
        foreach ($d['accounting_overrides'] ?? [] as $role => $code) {
            if (! in_array($role, AccountingAccountPolicy::ENTITY_ROLES[$kind], true)) {
                throw ValidationException::withMessages(['accounting' => __('This accounting role is not available for this operation.')]);
            }if (filled($code)) {
                app(AccountingAccountPolicy::class)->valid($code, $role);
                $result[$role] = $code;
            }
        }
        if ($kind === 'expense' && isset($result['supplier'],$result['creditor'])) {
            throw ValidationException::withMessages(['accounting' => __('Choose only one supplier or creditor control account for this expense.')]);
        }
        if ($result || ! empty($d['accounting_category_id'])) {
            $r->validate(['accounting_confirmation' => 'accepted']);
        }
        $d['accounting_overrides'] = $result;
        if (! in_array($kind, ['sale', 'purchase'], true) && ! empty($d['accounting_category_id'])) {
            throw ValidationException::withMessages(['accounting' => __('Financial category selection is not available for this operation.')]);
        }

        return $d;
    }

    public static function options(Request $r): ?array
    {
        if (! $r->user()?->can('assign-accounting-accounts') || ! $r->isMethod('GET') || ! $r->is('sales', 'sales/create', 'sales/*/edit', 'sales/*', 'billing/customers/*', 'billing/invoices/*', 'expenses', 'purchases')) {
            return null;
        }

        return ['accounts' => LedgerAccount::where('is_active', true)->where('is_postable', true)->orderBy('code')->get(['id', 'code', 'name', 'type']), 'categories' => FinancialCategory::where('is_active', true)->orderBy('name')->get(['id', 'name'])];
    }
}
