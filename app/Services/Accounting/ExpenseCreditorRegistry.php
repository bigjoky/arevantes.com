<?php

namespace App\Services\Accounting;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FiscalEntity;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseCreditorRegistry
{
    /** Register a master only; never overwrite an existing identity from an invoice snapshot. */
    public function supplier(array $data): ?Supplier
    {
        if (($data['type'] ?? null) !== 'invoice' || ! filled($data['issuer_tax_id'] ?? null)
            || ! filled($data['issuer_legal_name'] ?? null) || ($data['currency_code'] ?? 'EUR') !== 'EUR') {
            return null;
        }

        return DB::transaction(function () use ($data) {
            $country = $data['issuer_country'] ?? 'ES';
            $key = FiscalEntity::taxKey($data['issuer_tax_id']);
            $entity = FiscalEntity::firstOrCreate(['country' => $country, 'tax_id_key' => $key],
                ['tax_id' => $data['issuer_tax_id'], 'legal_name' => $data['issuer_legal_name'], 'trade_names' => [], 'addresses' => [], 'is_active' => true]);
            FiscalEntity::whereKey($entity->id)->lockForUpdate()->firstOrFail();
            $matches = Supplier::where('country', $country)
                ->whereRaw("upper(replace(replace(replace(tax_id, ' ', ''), '-', ''), '.', '')) = ?", [$key])->limit(2)->get();
            if ($matches->count() > 1) {
                throw ValidationException::withMessages(['supplier_id' => __('Multiple suppliers share this fiscal identity. Select and review the supplier explicitly.')]);
            }
            $supplier = $matches->first() ?? Supplier::create(['legal_name' => $data['issuer_legal_name'], 'tax_id' => $data['issuer_tax_id'],
                'country' => $country, 'is_active' => true]);
            if (! $supplier->is_active) {
                throw ValidationException::withMessages(['supplier_id' => __('The supplier is inactive. Review it before registering the invoice.')]);
            }
            app(SupplierLedgerAccountManager::class)->sync($supplier);

            return $supplier;
        });
    }

    /** New documents only. Historical account choices remain unchanged. */
    public function newInvoice(array $data, bool $includeDecision = false): array
    {
        if (($data['currency_code'] ?? 'EUR') !== 'EUR') {
            return $data;
        }
        $supplier = filled($data['supplier_id'] ?? null) ? Supplier::findOrFail($data['supplier_id']) : $this->supplier($data);
        if ($supplier) {
            app(SupplierLedgerAccountManager::class)->sync($supplier);
        }
        $category = ExpenseCategory::findOrFail($data['expense_category_id']);
        $overrides = $data['accounting_overrides'] ?? [];
        $frozen = $data['creditor_account_code'] ?? null;
        $role = isset($overrides['supplier']) ? 'supplier' : (isset($overrides['creditor']) ? 'creditor' : ($frozen ? (str_starts_with($frozen, '400') ? 'supplier' : 'creditor') : (str_starts_with($category->expense_account_code ?? '', '60') ? 'supplier' : 'creditor')));
        $legacy = $role === 'supplier' ? $supplier?->payable_account_code : $supplier?->service_payable_account_code;
        $policy = app(AccountingAccountPolicy::class);
        if ($frozen && ! isset($overrides['supplier']) && ! isset($overrides['creditor'])) {
            $account = $policy->valid($frozen, $role);
            $decision = ['role' => $role, 'code' => $account->code, 'name' => $account->name, 'origin' => 'stored_operation'];
        } else {
            $decision = $policy->decision($role, new Expense($data), $supplier, $category, legacySpecific: $legacy, fallback: $role === 'supplier' ? '400' : '410');
        }
        $result = [...$data, 'supplier_id' => $supplier?->id, 'creditor_account_code' => $decision['code']];
        if ($includeDecision) {
            $result['_accounting_control_decision'] = $decision;
        }

        return $result;
    }
}
