<?php

namespace App\Services\Accounting;

use App\Models\AccountingConfiguration;
use App\Models\LedgerAccount;
use Illuminate\Support\Facades\DB;

class AccountUsage
{
    public function forAccount(LedgerAccount $a): array
    {
        $usage = [];
        $fields = ['people' => ['receivable_account_code', 'employee_advance_account_code', 'employee_payable_account_code'], 'suppliers' => ['payable_account_code', 'service_payable_account_code'], 'products' => ['sale_account_code', 'purchase_account_code'], 'expense_categories' => ['expense_account_code'], 'tax_rates' => ['output_account_code', 'input_account_code'], 'expenses' => ['creditor_account_code'], 'accounting_template_lines' => ['account_code']];
        foreach ($fields as $table => $columns) {
            $count = DB::table($table)->where(function ($q) use ($columns, $a) {
                foreach ($columns as $c) {
                    $q->orWhere($c, $a->code);
                }
            })->count();
            if ($count) {
                $usage[] = ['type' => $table, 'count' => $count, 'href' => '/accounting/accounts/'.$a->id.'/usage?type='.$table];
            }
        }
        foreach (['bank_accounts', 'supplier_payments', 'bank_financial_products', 'bank_reconciliation_rules', 'journal_lines', 'accounting_account_assignments'] as $table) {
            $count = DB::table($table)->where('ledger_account_id', $a->id)->count();
            if ($count) {
                $usage[] = ['type' => $table, 'count' => $count, 'href' => '/accounting/accounts/'.$a->id.'/usage?type='.$table];
            }
        }
        $config = AccountingConfiguration::find(1);
        $count = collect($config?->defaults ?? [])->filter(fn ($c) => $c === $a->code)->count() + collect($config?->rules ?? [])->where('account_code', $a->code)->count();
        if ($count) {
            $usage[] = ['type' => 'configuration', 'count' => $count, 'href' => '/accounting/control'];
        }

        return $usage;
    }

    public const TYPES = ['people', 'suppliers', 'products', 'expense_categories', 'tax_rates', 'expenses', 'accounting_template_lines', 'bank_accounts', 'supplier_payments', 'bank_financial_products', 'bank_reconciliation_rules', 'journal_lines', 'accounting_account_assignments', 'configuration'];

    public function rows(LedgerAccount $account, string $type)
    {
        $fields = ['people' => ['receivable_account_code', 'employee_advance_account_code', 'employee_payable_account_code'], 'suppliers' => ['payable_account_code', 'service_payable_account_code'], 'products' => ['sale_account_code', 'purchase_account_code'], 'expense_categories' => ['expense_account_code'], 'tax_rates' => ['output_account_code', 'input_account_code'], 'expenses' => ['creditor_account_code'], 'accounting_template_lines' => ['account_code']];
        $q = DB::table($type);
        if (isset($fields[$type])) {
            $q->where(function ($q) use ($fields, $type, $account) {
                foreach ($fields[$type] as $field) {
                    $q->orWhere($field, $account->code);
                }
            });
        } else {
            $q->where('ledger_account_id', $account->id);
        }

        return $q->orderBy('id')->paginate(25)->withQueryString()->through(function ($row) use ($type) {
            $kind = ['people' => 'customer', 'suppliers' => 'supplier', 'products' => 'product', 'expense_categories' => 'expense_category', 'tax_rates' => 'tax_rate', 'expenses' => 'expense', 'bank_accounts' => 'bank', 'bank_financial_products' => 'financial_product'][$type] ?? null;
            $href = $kind ? '/accounting/control?entity='.$kind.'&entity_id='.$row->id : null;
            if ($type === 'journal_lines') {
                $href = '/accounting/entries/'.$row->journal_entry_id;
            }
            if ($type === 'accounting_template_lines') {
                $href = '/accounting/templates';
            }
            if ($type === 'bank_reconciliation_rules') {
                $href = '/banking?tab=rules&account='.$row->bank_account_id;
            }
            if ($type === 'supplier_payments') {
                $href = $row->expense_id ? '/expenses/'.$row->expense_id : ($row->purchase_document_id ? '/purchases/'.$row->purchase_document_id : null);
            }
            if ($type === 'accounting_account_assignments') {
                $target = array_search($row->entity_type, AccountingAccountPolicy::ENTITIES, true);
                $href = $target ? '/accounting/control?entity='.$target.'&entity_id='.$row->entity_id : null;
            }
            if ($type === 'people' && $row->deleted_at) {
                $href = null;
            }

            return ['id' => $row->id, 'label' => $row->legal_name ?? $row->company_name ?? $row->name ?? $row->number ?? $row->reference ?? $row->description ?? trim(($row->first_name ?? '').' '.($row->last_name ?? '')), 'href' => $href];
        });
    }
}
