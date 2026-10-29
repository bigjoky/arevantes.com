<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccountAssignment;
use App\Models\AccountingConfiguration;
use App\Models\BankAccount;
use App\Models\BankFinancialProduct;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialCategory;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Future posting decisions only. Never allocates an account or rewrites a posted line. */
class AccountingAccountPolicy
{
    public const DEFAULTS = ['receivable' => '430', 'supplier' => '400', 'creditor' => '410', 'bank' => '572', 'cash' => '570', 'revenue' => '700', 'purchase' => '600', 'expense' => '629', 'customer_advance' => '438', 'supplier_advance' => '407', 'output_vat' => '477', 'input_vat' => '472', 'withholding' => '4751', 'employee_advance' => '460', 'employee_payable' => '465'];

    public const ROLES = [
        'receivable' => ['430', 'asset'], 'supplier' => ['400', 'liability'], 'creditor' => ['410', 'liability'],
        'credit_card' => ['520', 'liability'], 'treasury_other' => ['57', 'asset'], 'bank' => ['572', 'asset'], 'cash' => ['570', 'asset'], 'revenue' => ['7', 'income'], 'purchase' => ['6', 'expense'],
        'expense' => ['6', 'expense'], 'inventory' => ['3', 'asset'], 'fixed_asset' => ['2', 'asset'],
        'customer_advance' => ['438', 'liability'], 'supplier_advance' => ['407', 'asset'],
        'output_vat' => ['477', 'liability'], 'input_vat' => ['472', 'asset'], 'withholding' => ['4751', 'liability'],
        'employee_advance' => ['460', 'asset'], 'employee_payable' => ['465', 'liability'],
    ];

    public const ENTITIES = [
        'customer' => Person::class, 'supplier' => Supplier::class, 'product' => Product::class,
        'financial_product' => BankFinancialProduct::class, 'bank' => BankAccount::class, 'expense_category' => ExpenseCategory::class, 'tax_rate' => TaxRate::class,
        'financial_category' => FinancialCategory::class, 'sale' => SalesDocument::class,
        'purchase' => PurchaseDocument::class, 'expense' => Expense::class,
        'receipt' => SalesDocumentPayment::class, 'payment' => SupplierPayment::class,
    ];

    public const ENTITY_ROLES = [
        'customer' => ['receivable', 'customer_advance', 'employee_advance', 'employee_payable'],
        'supplier' => ['supplier', 'creditor', 'supplier_advance'], 'product' => ['revenue', 'purchase', 'inventory', 'fixed_asset'],
        'financial_product' => ['credit_card'], 'bank' => ['bank'], 'expense_category' => ['expense'], 'tax_rate' => ['output_vat', 'input_vat'],
        'financial_category' => ['revenue', 'expense', 'inventory', 'fixed_asset'],
        'sale' => ['receivable', 'revenue', 'customer_advance'], 'purchase' => ['supplier', 'purchase', 'inventory', 'fixed_asset', 'supplier_advance'],
        'expense' => ['supplier', 'creditor', 'expense', 'withholding'], 'receipt' => ['receivable', 'customer_advance', 'bank', 'cash'], 'payment' => ['supplier', 'creditor', 'supplier_advance', 'bank', 'cash', 'credit_card', 'employee_payable', 'treasury_other'],
    ];

    public static function entity(string $kind, int $id): Model
    {
        abort_unless(isset(self::ENTITIES[$kind]), 404);

        return self::ENTITIES[$kind]::findOrFail($id);
    }

    public static function nature(LedgerAccount $account): string
    {
        // The legacy PGC import inherited group nature for these two exceptions.
        // Validate their actual PGC role without rewriting historical account metadata.
        if (str_starts_with($account->code, '438')) {
            return 'liability';
        }
        if (str_starts_with($account->code, '407')) {
            return 'asset';
        }

        return $account->type;
    }

    public static function natureExpression(string $table = 'ledger_accounts'): string
    {
        return "CASE WHEN $table.code LIKE '438%' THEN 'liability' WHEN $table.code LIKE '407%' THEN 'asset' ELSE $table.type END";
    }

    public function valid(string $code, string $role): LedgerAccount
    {
        $definition = self::ROLES[$role] ?? null;
        $a = LedgerAccount::where('code', $code)->first();
        if (! $definition || ! $a?->is_active || ! $a->is_postable || self::nature($a) !== $definition[1] || ! str_starts_with($a->code, $definition[0])) {
            throw ValidationException::withMessages(['accounting' => __('Select an active postable account compatible with :role.', ['role' => $role])]);
        }

        return $a;
    }

    public function assigned(?Model $entity, string $role): ?string
    {
        if (! $entity || ! $entity->id) {
            return null;
        }
        $id = AccountingAccountAssignment::where('entity_type', $entity->getMorphClass())->where('entity_id', $entity->id)->where('role', $role)->value('ledger_account_id');

        return $id ? LedgerAccount::whereKey($id)->value('code') : null;
    }

    public function decision(string $role, ?Model $operation = null, ?Model $specific = null, ?Model $category = null, ?string $legacySpecific = null, ?string $categoryCode = null, ?string $fallback = null): array
    {
        $config = AccountingConfiguration::query()->when(DB::transactionLevel() > 0, fn ($q) => $q->sharedLock())->find(1);
        $code = $operation?->getAttribute('accounting_overrides')[$role] ?? $this->assigned($operation, $role);
        $origin = 'operation';
        $position = null;
        if (! $code) {
            $code = $this->assigned($specific, $role) ?: $legacySpecific;
            $origin = 'specific';
        }
        if (! $code) {
            foreach ($config?->rules ?? [] as $index => $rule) {
                if (! ($rule['active'] ?? true) || ($rule['role'] ?? null) !== $role) {
                    continue;
                }
                if (($rule['operation_type'] ?? '') !== '' && ($rule['operation_type'] ?? '') !== $operation?->getMorphClass()) {
                    continue;
                }
                if (($rule['product_type'] ?? '') !== '' && ($rule['product_type'] ?? '') !== $specific?->getAttribute('type')) {
                    continue;
                }
                if (($rule['category_id'] ?? null) !== null && ((int) $rule['category_id'] !== (int) $category?->id || ($rule['category_type'] ?? null) !== $category?->getMorphClass())) {
                    continue;
                }
                $code = $rule['account_code'];
                $origin = 'rule';
                $position = $index + 1;
                break;
            }
        }
        if (! $code) {
            $code = $this->assigned($category, $role) ?: $categoryCode;
            $origin = 'category';
        }
        if (! $code) {
            $code = $config?->defaults[$role] ?? $fallback;
            $origin = 'system';
        }
        if (! $code) {
            throw ValidationException::withMessages(['accounting' => __('An accounting assignment is required for :role.', ['role' => $role])]);
        }
        $a = $this->valid((string) $code, $role);

        return ['code' => $a->code, 'name' => $a->name, 'origin' => $origin, 'role' => $role, 'generic' => strlen($a->code) <= 4, 'rule_position' => $position, 'configuration_revision' => $config?->revision ?? 0];
    }

    public function code(string $role, ?Model $operation = null, ?Model $specific = null, ?Model $category = null, ?string $legacySpecific = null, ?string $categoryCode = null, ?string $fallback = null): string
    {
        return $this->decision($role, $operation, $specific, $category, $legacySpecific, $categoryCode, $fallback)['code'];
    }
}
