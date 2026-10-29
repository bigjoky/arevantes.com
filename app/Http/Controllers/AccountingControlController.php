<?php

namespace App\Http\Controllers;

use App\Models\AccountingAccountAssignment;
use App\Models\AccountingConfiguration;
use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Models\ExpenseCategory;
use App\Models\FinancialCategory;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Services\Accounting\AccountingAccountPolicy;
use App\Services\Accounting\AccountingReview;
use App\Services\Accounting\AccountUsage;
use App\Services\Accounting\JournalConcepts;
use App\Services\Accounting\JournalCorrection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AccountingControlController extends Controller
{
    private function singleCompany(): void
    {
        abort_if(CompanyProfile::count() > 1, 409, __('Accounting company separation is not configured.'));
    }

    private function authorize(Request $r): void
    {
        $this->singleCompany();
        abort_unless($r->user()->can('configure-accounting'), 403);
    }

    public function index(Request $r)
    {
        $this->singleCompany();
        $config = AccountingConfiguration::find(1);

        return Inertia::render('accounting/control', ['configuration' => ['revision' => $config?->revision ?? 0, 'defaults' => array_replace(AccountingAccountPolicy::DEFAULTS, $config?->defaults ?? []), 'rules' => $config?->rules ?? [], 'templates' => array_replace_recursive(JournalConcepts::DEFAULTS, $config?->templates ?? [])],
            'categories' => FinancialCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']), 'expenseCategories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']), 'roles' => AccountingAccountPolicy::ROLES, 'entities' => array_keys(AccountingAccountPolicy::ENTITIES), 'entityTypes' => AccountingAccountPolicy::ENTITIES, 'accounts' => LedgerAccount::where('is_active', true)->where('is_postable', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'canAssign' => $r->user()->can('assign-accounting-accounts'), 'canManageAccounts' => $r->user()->can('manage-ledger-accounts'), 'canConfigure' => $r->user()->can('configure-accounting'), 'selectedEntity' => $r->only(['entity', 'entity_id'])]);
    }

    public function save(Request $r, AccountingAccountPolicy $policy)
    {
        $this->authorize($r);
        $d = $r->validate(['revision' => 'required|integer|min:0', 'reason' => 'required|string|min:5|max:2000', 'confirmed' => 'accepted',
            'defaults' => 'present|array', 'defaults.*' => 'nullable|string|max:20', 'rules' => 'present|array|max:50', 'rules.*' => 'array:role,operation_type,product_type,category_id,category_type,account_code,active',
            'rules.*.role' => ['required', Rule::in(array_keys(AccountingAccountPolicy::ROLES))], 'rules.*.operation_type' => ['nullable', Rule::in(array_values(AccountingAccountPolicy::ENTITIES))],
            'rules.*.product_type' => 'nullable|string|in:product,service', 'rules.*.category_id' => 'nullable|integer|min:1', 'rules.*.category_type' => ['nullable', Rule::in([ExpenseCategory::class, FinancialCategory::class])],
            'rules.*.account_code' => 'required|string|max:20', 'rules.*.active' => 'required|boolean',
            'templates' => 'required|array', 'templates.*' => 'required|array:es,en', 'templates.*.es' => 'required|string|max:255', 'templates.*.en' => 'required|string|max:255']);
        foreach ($d['defaults'] as $role => $code) {
            if (! isset(AccountingAccountPolicy::ROLES[$role])) {
                abort(422);
            }if (filled($code)) {
                $policy->valid($code, $role);
            }
        }
        foreach ($d['rules'] as $rule) {
            $policy->valid($rule['account_code'], $rule['role']);
            if (! empty($rule['category_id'])) {
                if (empty($rule['category_type'])) {
                    abort(422);
                }$rule['category_type']::findOrFail($rule['category_id']);
            }
        }
        if (array_diff(array_keys($d['templates']), array_keys(JournalConcepts::DEFAULTS))) {
            abort(422);
        }
        foreach ($d['templates'] as $template) {
            foreach ($template as $text) {
                preg_match_all('/\{([^{}]+)\}/u', $text, $m);
                if (array_diff($m[1], JournalConcepts::TOKENS)) {
                    throw ValidationException::withMessages(['templates' => __('The concept template contains an unsupported placeholder.')]);
                }
            }
        }
        DB::transaction(function () use ($r, $d) {
            // Serialize singleton creation and all configuration revisions without touching history.
            LedgerAccount::orderBy('id')->lockForUpdate()->firstOrFail();
            $c = AccountingConfiguration::whereKey(1)->lockForUpdate()->first();
            if (($c?->revision ?? 0) !== (int) $d['revision']) {
                throw ValidationException::withMessages(['revision' => __('The configuration changed. Reload before saving.')]);
            }
            $before = $c?->only(['defaults', 'rules', 'templates', 'revision']);
            AccountingConfiguration::updateOrCreate(['id' => 1], ['defaults' => array_filter($d['defaults'], fn ($v) => filled($v)), 'rules' => $d['rules'], 'templates' => $d['templates'], 'revision' => ($c?->revision ?? 0) + 1, 'updated_by' => $r->user()->id]);
            AuditLog::create(['user_id' => $r->user()->id, 'event' => 'accounting_configuration_changed', 'auditable_type' => AccountingConfiguration::class, 'auditable_id' => 1, 'old_values' => $before, 'new_values' => ['defaults' => $d['defaults'], 'rules' => $d['rules'], 'templates' => $d['templates'], 'reason' => $d['reason'], 'future_only' => true]]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Accounting configuration saved for future operations.')]);
    }

    public function entities(Request $r, string $kind)
    {
        $this->singleCompany();
        abort_unless(isset(AccountingAccountPolicy::ENTITIES[$kind]), 404);
        $r->validate(['search' => 'nullable|string|max:100']);
        $q = AccountingAccountPolicy::ENTITIES[$kind]::query();
        $columns = match ($kind) {
            'customer' => ['company_name', 'first_name', 'last_name', 'tax_id'],'supplier' => ['legal_name', 'trade_name', 'tax_id'],'product' => ['name', 'sku'],'financial_product','bank','expense_category','tax_rate','financial_category' => ['name'],default => ['id']
        };
        if ($term = trim($r->string('search')->toString())) {
            $q->where(function ($q) use ($columns, $term) {
                foreach ($columns as $c) {
                    if ($c === 'id') {
                        $q->orWhere('id', ctype_digit($term) ? (int) $term : 0);
                    } else {
                        $q->orWhere($c, 'like', '%'.$term.'%');
                    }
                }
            });
        }
        if ($kind === 'financial_product') {
            $q->where('type', 'credit_card');
        }
        if ($kind === 'customer') {
            $q->whereIn('category', ['customer', 'staff']);
        }

        return response()->json($q->orderByDesc('id')->paginate(15)->through(fn ($m) => ['id' => $m->id, 'label' => $this->label($m)]));
    }

    private function label($m): string
    {
        return (string) ($m->legal_name ?? $m->company_name ?? $m->name ?? $m->number ?? trim(($m->first_name ?? '').' '.($m->last_name ?? '')) ?: '#'.$m->id);
    }

    private function legacy(string $kind, string $role): ?string
    {
        return ['customer' => ['receivable' => 'receivable_account_code', 'employee_advance' => 'employee_advance_account_code', 'employee_payable' => 'employee_payable_account_code'],
            'supplier' => ['supplier' => 'payable_account_code', 'creditor' => 'service_payable_account_code'], 'product' => ['revenue' => 'sale_account_code', 'purchase' => 'purchase_account_code'],
            'expense_category' => ['expense' => 'expense_account_code'], 'tax_rate' => ['output_vat' => 'output_account_code', 'input_vat' => 'input_account_code'], 'expense' => ['supplier' => 'creditor_account_code', 'creditor' => 'creditor_account_code']][$kind][$role] ?? null;
    }

    public function assignments(Request $r, string $kind, int $id, AccountingAccountPolicy $policy)
    {
        $this->singleCompany();
        $m = AccountingAccountPolicy::entity($kind, $id);
        $rows = [];
        foreach (AccountingAccountPolicy::ENTITY_ROLES[$kind] as $role) {
            $field = $this->legacy($kind, $role);
            $code = $m->getAttribute('accounting_overrides')[$role] ?? ($policy->assigned($m, $role) ?: ($field ? $m->$field : null));
            if (in_array($kind, ['bank', 'financial_product'])) {
                $code = $m->ledgerAccount?->code;
            }
            $prefix = AccountingAccountPolicy::ROLES[$role][0];
            $candidate = $prefix.str_pad((string) $m->id, 4, '0', STR_PAD_LEFT);
            $i = 0;
            while (LedgerAccount::where('code', $candidate)->exists() && $i < 1000) {
                $candidate = $prefix.str_pad((string) ($m->id + (++$i)), 4, '0', STR_PAD_LEFT);
            }
            $rows[] = ['role' => $role, 'code' => $code, 'name' => $code ? LedgerAccount::where('code', $code)->value('name') : null, 'proposal' => ['code' => $candidate, 'name' => $this->label($m), 'prefix' => $prefix], 'origin' => $code ? 'specific' : 'system'];
        }

        return response()->json(['kind' => $kind, 'id' => $id, 'label' => $this->label($m), 'assignments' => $rows, 'revision' => $this->assignmentRevision($m), 'future_only' => true, 'posted_entry_id' => JournalEntry::whereMorphedTo('source', $m)->value('id'), 'category_id' => $m->getAttribute('accounting_category_id')]);
    }

    private function assignmentRevision($m): string
    {
        return hash('sha256', json_encode([$m->getAttributes(), AccountingAccountAssignment::where('entity_type', $m->getMorphClass())->where('entity_id', $m->id)->orderBy('role')->get()->toArray()], JSON_THROW_ON_ERROR));
    }

    public function assign(Request $r, string $kind, int $id, AccountingAccountPolicy $policy)
    {
        $this->singleCompany();
        abort_unless($r->user()->can('assign-accounting-accounts'), 403);
        $m = AccountingAccountPolicy::entity($kind, $id);
        $d = $r->validate(['role' => ['required', Rule::in(AccountingAccountPolicy::ENTITY_ROLES[$kind])], 'account_code' => 'nullable|string|max:20', 'revision' => 'required|string|size:64', 'reason' => 'required|string|min:5|max:2000', 'confirmed' => 'accepted']);
        DB::transaction(function () use ($m, $d, $r, $policy, $kind) {
            $m = $m->newQuery()->whereKey($m->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals($this->assignmentRevision($m), $d['revision'])) {
                throw ValidationException::withMessages(['revision' => __('The accounting assignment changed. Reload before saving.')]);
            }
            if (in_array($kind, ['sale', 'purchase', 'expense', 'receipt', 'payment']) && JournalEntry::whereMorphedTo('source', $m)->exists()) {
                throw ValidationException::withMessages(['accounting' => __('This operation is already posted. Use its journal correction workflow.')]);
            }
            if (blank($d['account_code'] ?? null)) {
                if (in_array($kind, ['bank', 'financial_product'])) {
                    throw ValidationException::withMessages(['accounting' => __('Select an explicit treasury account before continuing.')]);
                }
                $old = AccountingAccountAssignment::where('entity_type', $m->getMorphClass())->where('entity_id', $m->id)->where('role', $d['role'])->first()?->toArray();
                AccountingAccountAssignment::where('entity_type', $m->getMorphClass())->where('entity_id', $m->id)->where('role', $d['role'])->delete();
                if ($field = $this->legacy($kind, $d['role'])) {
                    $m->update([$field => null]);
                }
                if (in_array($kind, ['sale', 'purchase', 'expense', 'receipt', 'payment'])) {
                    $overrides = $m->accounting_overrides ?? [];
                    unset($overrides[$d['role']]);
                    $m->update(['accounting_overrides' => $overrides]);
                }
                AuditLog::create(['user_id' => $r->user()->id, 'event' => 'accounting_assignment_delegated', 'auditable_type' => $m->getMorphClass(), 'auditable_id' => $m->id, 'old_values' => $old, 'new_values' => ['role' => $d['role'], 'reason' => $d['reason'], 'future_only' => true]]);

                return;
            }
            if ($kind === 'expense' && in_array($d['role'], ['supplier', 'creditor'], true)) {
                $other = $d['role'] === 'supplier' ? 'creditor' : 'supplier';
                if (filled($m->accounting_overrides[$other] ?? null) || $policy->assigned($m, $other)) {
                    throw ValidationException::withMessages(['accounting' => __('Select only one supplier or creditor control account.')]);
                }
            }
            $a = $policy->valid($d['account_code'], $d['role']);
            LedgerAccount::whereKey($a->id)->sharedLock()->firstOrFail();
            $before = AccountingAccountAssignment::where('entity_type', $m->getMorphClass())->where('entity_id', $m->id)->where('role', $d['role'])->first()?->toArray();
            AccountingAccountAssignment::updateOrCreate(['entity_type' => $m->getMorphClass(), 'entity_id' => $m->id, 'role' => $d['role']], ['ledger_account_id' => $a->id, 'updated_by' => $r->user()->id]);
            if (in_array($kind, ['sale', 'purchase', 'expense', 'receipt', 'payment'])) {
                $m->update(['accounting_overrides' => [...($m->accounting_overrides ?? []), $d['role'] => $a->code]]);
            }
            if ($field = $this->legacy($kind, $d['role'])) {
                $m->update([$field => $a->code]);
            }
            if (in_array($kind, ['bank', 'financial_product'])) {
                $m->update(['ledger_account_id' => $a->id]);
            }
            AuditLog::create(['user_id' => $r->user()->id, 'event' => 'accounting_assignment_changed', 'auditable_type' => $m->getMorphClass(), 'auditable_id' => $m->id, 'old_values' => $before, 'new_values' => ['role' => $d['role'], 'account_code' => $a->code, 'reason' => $d['reason'], 'future_only' => true]]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Accounting assignment saved for future operations.')]);
    }

    public function category(Request $r, string $kind, int $id)
    {
        $this->singleCompany();
        abort_unless($r->user()->can('assign-accounting-accounts') && in_array($kind, ['sale', 'purchase']), 403);
        $d = $r->validate(['category_id' => ['nullable', Rule::exists('financial_categories', 'id')->where('is_active', true)], 'revision' => 'required|string|size:64', 'reason' => 'required|string|min:5|max:2000', 'confirmed' => 'accepted']);
        $m = AccountingAccountPolicy::entity($kind, $id);
        DB::transaction(function () use ($m, $d, $r) {
            $m = $m->newQuery()->whereKey($m->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals($this->assignmentRevision($m), $d['revision'])) {
                throw ValidationException::withMessages(['revision' => __('The accounting assignment changed. Reload before saving.')]);
            }
            if (JournalEntry::whereMorphedTo('source', $m)->exists()) {
                throw ValidationException::withMessages(['accounting' => __('This operation is already posted. Use its journal correction workflow.')]);
            }
            $before = $m->accounting_category_id;
            $m->update(['accounting_category_id' => $d['category_id'] ?? null]);
            AuditLog::create(['user_id' => $r->user()->id, 'event' => 'accounting_category_assigned', 'auditable_type' => $m->getMorphClass(), 'auditable_id' => $m->id, 'old_values' => ['accounting_category_id' => $before], 'new_values' => ['accounting_category_id' => $m->accounting_category_id, 'reason' => $d['reason'], 'future_only' => true]]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Accounting category saved for future posting.')]);
    }

    private function correctionData(Request $r): array
    {
        return $r->validate(['amount' => 'prohibited', 'debit' => 'prohibited', 'credit' => 'prohibited', 'person_id' => 'prohibited', 'supplier_id' => 'prohibited', 'issue_date' => 'prohibited', 'entry_date' => 'required|date_format:Y-m-d', 'description' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:2000', 'reference' => 'nullable|string|max:255', 'reason' => 'required|string|min:5|max:2000', 'operation_key' => 'required|uuid', 'revision' => 'required|string|size:64', 'lines' => 'present|array|max:50', 'lines.*' => 'array:id,account_code,description', 'lines.*.id' => 'required|integer|distinct', 'lines.*.account_code' => 'required|string|max:20', 'lines.*.description' => 'present|nullable|string|max:255']);
    }

    public function preview(Request $r, JournalEntry $entry, JournalCorrection $service)
    {
        $this->singleCompany();
        abort_unless($r->user()->can('correct-journal-entries'), 403);
        $d = $this->correctionData($r);

        return response()->json($service->plan($entry, $d));
    }

    public function correct(Request $r, JournalEntry $entry, JournalCorrection $service)
    {
        $this->singleCompany();
        $r->validate(['confirmed' => 'accepted']);
        $c = $service->correct($entry, $this->correctionData($r), $r->user());

        return to_route('accounting.entries.show', $entry)->with('toast', ['type' => 'success', 'message' => __('Journal correction recorded; the original remains unchanged.')]);
    }

    public function usage(Request $r, LedgerAccount $account, AccountUsage $usage)
    {
        $this->singleCompany();
        $d = $r->validate(['type' => ['required', Rule::in($usage::TYPES)]]);
        if ($d['type'] === 'configuration') {
            return to_route('accounting.control');
        }

        return Inertia::render('accounting/account-usage', ['account' => $account->only(['id', 'code', 'name']), 'type' => $d['type'], 'usages' => $usage->forAccount($account), 'rows' => $usage->rows($account, $d['type'])]);
    }

    public function review(Request $r, AccountingReview $report)
    {
        $this->singleCompany();
        $d = $r->validate(['issue' => ['nullable', Rule::in($report::ISSUES)], 'search' => 'nullable|string|max:100']);
        $issue = $d['issue'] ?? 'missing_party';
        $counts = [];
        foreach ($report::ISSUES as $key) {
            $counts[$key] = $report->query($key)->count();
        }
        $rows = $report->query($issue)->when($d['search'] ?? null, fn ($q, $term) => $q->where('title', 'like', '%'.$term.'%'))->orderBy('id')->paginate(25)->withQueryString();

        return Inertia::render('accounting/review', ['issue' => $issue, 'counts' => $counts, 'rows' => $rows, 'search' => $d['search'] ?? '']);
    }
}
