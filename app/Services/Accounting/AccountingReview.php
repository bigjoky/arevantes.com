<?php

namespace App\Services\Accounting;

use App\Models\AccountingConfiguration;
use Illuminate\Support\Facades\DB;

class AccountingReview
{
    public const ISSUES = ['missing_party', 'generic_accounts', 'duplicate_accounts', 'incompatible_assignments', 'poor_concepts', 'missing_references', 'pending_classification', 'inactive_rules'];

    private function accountRows($q)
    {
        return $q->selectRaw("id, code || ' · ' || name as title, '/accounting/accounts/' || cast(id as text) as href");
    }

    public function query(string $issue)
    {
        if ($issue === 'missing_party') {
            $q = DB::table('people')->whereNull('deleted_at')->where('category', 'customer')->whereNull('receivable_account_code')->selectRaw("id, coalesce(company_name, first_name || ' ' || coalesce(last_name,'')) as title, '/accounting/control?entity=customer&entity_id=' || cast(id as text) as href");
            $q->unionAll(DB::table('suppliers')->where(fn ($q) => $q->whereNull('payable_account_code')->orWhereNull('service_payable_account_code'))->selectRaw("id, legal_name as title, '/accounting/control?entity=supplier&entity_id=' || cast(id as text) as href"));
        } elseif ($issue === 'generic_accounts') {
            $q = $this->accountRows(DB::table('ledger_accounts')->whereRaw('length(code)<=4')->whereExists(fn ($s) => $s->selectRaw('1')->from('journal_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')->whereColumn('journal_lines.ledger_account_id', 'ledger_accounts.id')->where('journal_entries.status', 'posted')));
        } elseif ($issue === 'duplicate_accounts') {
            $q = $this->accountRows(DB::table('ledger_accounts')->whereIn(DB::raw('lower(trim(name))'), DB::table('ledger_accounts')->selectRaw('lower(trim(name))')->groupByRaw('lower(trim(name))')->havingRaw('count(*)>1')));
        } elseif ($issue === 'incompatible_assignments') {
            $q = null;
            foreach (['people' => ['receivable_account_code' => 'receivable'], 'suppliers' => ['payable_account_code' => 'supplier', 'service_payable_account_code' => 'creditor'], 'products' => ['sale_account_code' => 'revenue', 'purchase_account_code' => 'purchase'], 'expense_categories' => ['expense_account_code' => 'expense'], 'tax_rates' => ['output_account_code' => 'output_vat', 'input_account_code' => 'input_vat']] as $table => $fields) {
                foreach ($fields as $field => $role) {
                    [$prefix,$type] = AccountingAccountPolicy::ROLES[$role];
                    $kind = ['people' => 'customer', 'suppliers' => 'supplier', 'products' => 'product', 'expense_categories' => 'expense_category', 'tax_rates' => 'tax_rate'][$table];
                    $part = DB::table($table.' as entity')->leftJoin('ledger_accounts as a', 'a.code', '=', 'entity.'.$field)->whereNotNull('entity.'.$field)->where(fn ($s) => $s->whereNull('a.id')->orWhere('a.is_active', false)->orWhere('a.is_postable', false)->orWhere('a.type', '!=', $type)->orWhere('a.code', 'not like', $prefix.'%'))->selectRaw("entity.id, entity.$field as title, '/accounting/control?entity=$kind&entity_id=' || cast(entity.id as text) as href");
                    if ($q) {
                        $q->unionAll($part);
                    } else {
                        $q = $part;
                    }
                }
            }
            foreach (AccountingAccountPolicy::ROLES as $role => [$prefix,$type]) {
                $part = DB::table('accounting_account_assignments as s')->join('ledger_accounts as a', 'a.id', '=', 's.ledger_account_id')->where('s.role', $role)->where(fn ($q) => $q->where('a.is_active', false)->orWhere('a.is_postable', false)->orWhere('a.type', '!=', $type)->orWhere('a.code', 'not like', $prefix.'%'))->selectRaw("s.id, a.code || ' · ' || a.name as title, '/accounting/control' as href");
                $q->unionAll($part);
            }
        } elseif ($issue === 'poor_concepts') {
            $q = DB::table('journal_entries')->where('status', 'posted')->where(fn ($q) => $q->whereNull('description')->orWhereRaw('length(trim(description))<8')->orWhere('description', 'like', 'Invoice created%')->orWhere('description', 'like', 'Payment processed%')->orWhere('description', 'like', 'Auto journal%')->orWhere('description', 'like', 'App\\%'))->selectRaw("id, number as title, '/accounting/entries/' || cast(id as text) as href");
        } elseif ($issue === 'missing_references') {
            $q = DB::table('purchase_documents')->whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->whereNotIn('status', ['draft', 'cancelled'])->where(fn ($q) => $q->whereNull('supplier_number')->orWhere('supplier_number', ''))->selectRaw("id, number as title, '/purchases/' || cast(id as text) as href");
            $q->unionAll(DB::table('expenses')->where('type', 'invoice')->where(fn ($q) => $q->whereNull('document_number')->orWhere('document_number', ''))->selectRaw("id, number as title, '/expenses/' || cast(id as text) as href"));
        } elseif ($issue === 'pending_classification') {
            $q = DB::table('bank_transactions')->whereIn('status', ['pending', 'partial'])->selectRaw("id, coalesce(reference,description) as title, '/banking?tab=reconciliation&account=' || cast(bank_account_id as text) as href");
        } else {
            $q = DB::table('bank_reconciliation_rules as r')->join('ledger_accounts as a', 'a.id', '=', 'r.ledger_account_id')->where('r.is_active', true)->where(fn ($s) => $s->where('a.is_active', false)->orWhere('a.is_postable', false))->selectRaw("r.id, r.name as title, '/banking?tab=rules&account=' || cast(r.bank_account_id as text) as href");
            $q->unionAll(DB::table('accounting_template_lines as l')->join('accounting_templates as t', 't.id', '=', 'l.accounting_template_id')->join('ledger_accounts as a', 'a.code', '=', 'l.account_code')->where('t.is_active', true)->where(fn ($s) => $s->where('a.is_active', false)->orWhere('a.is_postable', false))->selectRaw("t.id, t.name as title, '/accounting/templates' as href"));
            $c = AccountingConfiguration::find(1);
            foreach ($c?->rules ?? [] as $i => $rule) {
                if (($rule['active'] ?? true) && DB::table('ledger_accounts')->where('code', $rule['account_code'])->where(fn ($s) => $s->where('is_active', false)->orWhere('is_postable', false))->exists()) {
                    $q->unionAll(DB::query()->selectRaw('? as id, ? as title, ? as href', [1, $rule['account_code'], '/accounting/control']));
                }
            }
        }

        return DB::query()->fromSub($q, 'review_items');
    }
}
