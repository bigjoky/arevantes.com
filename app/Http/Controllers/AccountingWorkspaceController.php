<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\AccountingWorkspace;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingWorkspaceController extends Controller
{
    private function filters(Request $request): array
    {
        abort_if(CompanyProfile::count() > 1, 409, __('Accounting company separation is not configured.'));
        $f = $request->validate([
            'year' => 'nullable|integer|between:2020,2100', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'search' => 'nullable|string|max:150', 'entry_status' => ['nullable', Rule::in(['posted', 'draft'])],
            'kind' => 'nullable|string|max:64',
            'sort' => ['nullable', Rule::in(['date_desc', 'date_asc', 'number'])], 'account' => 'nullable|string|max:20|exists:ledger_accounts,code',
            'group' => 'nullable|integer|between:1,9', 'active' => ['nullable', Rule::in(['yes', 'no'])],
            'accounts_page' => 'nullable|integer|min:1',
            'tab' => ['nullable', Rule::in(['all', 'groups', 'operating', 'financial', 'commercial', 'tax', 'settings', 'import'])],
            'page' => 'nullable|integer|min:1', 'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);
        $f['year'] = (int) ($f['year'] ?? now()->year);
        $fy = FiscalYear::where('year', $f['year'])->first();
        $f['from'] = $f['from'] ?? $fy?->starts_on->toDateString() ?? $f['year'].'-01-01';
        $f['to'] = $f['to'] ?? $fy?->ends_on->toDateString() ?? $f['year'].'-12-31';
        abort_unless($f['from'] <= $f['to'], 422);
        $start = $fy?->starts_on->toDateString() ?? $f['year'].'-01-01';
        $end = $fy?->ends_on->toDateString() ?? $f['year'].'-12-31';
        abort_unless($f['from'] >= $start && $f['to'] <= $end, 422, __('The selected dates must belong to the fiscal year.'));

        return $f;
    }

    public function index(Request $request, AccountingWorkspace $workspace): Response
    {
        $f = $this->filters($request);
        $area = $request->is('accounting/accounts*') ? 'accounts' : ($request->is('workspace/modules/accounting/books') ? 'books' : $request->input('area', ($request->filled('account') || in_array($request->input('view'), ['balance', 'profit', 'ledger', 'trial', 'equity', 'cashflow'], true)) ? 'reports' : 'entries'));
        abort_unless(in_array($area, ['overview', 'entries', 'accounts', 'reports', 'books'], true), 404);
        $view = $request->input('view', $request->filled('account') ? 'ledger' : ($area === 'reports' ? 'balance' : 'journal'));
        abort_unless(in_array($view, ['balance', 'profit', 'journal', 'ledger', 'trial', 'equity', 'cashflow'], true), 404);
        $entries = $workspace->entryQuery()->whereDate('entry_date', '>=', $f['from'])->whereDate('entry_date', '<=', $f['to'])
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', "%$s%")->orWhere('description', 'like', "%$s%")))
            ->when($f['entry_status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($f['kind'] ?? null, fn ($q, $s) => $q->where('entry_kind', $s))
            ->when($f['account'] ?? null, fn ($q, $a) => $q->whereHas('lines.account', fn ($q) => $q->where('code', $a)));
        if (in_array($area, ['reports', 'overview'], true)) {
            $entries->where('status', 'posted');
        }
        $sort = $f['sort'] ?? ($area === 'reports' ? 'date_asc' : 'date_desc');
        $entries->orderBy($sort === 'number' ? 'number' : 'entry_date', $sort === 'date_desc' ? 'desc' : 'asc')->orderBy('id', $sort === 'date_desc' ? 'desc' : 'asc');
        if ($area === 'reports' && $view === 'journal') {
            $entries->with('lines.account:id,code,name');
        }
        $pagedEntries = $entries->paginate($f['per_page'] ?? 10)->withQueryString()->through(function ($entry) use ($workspace) {
            return [...$workspace->entryRow($entry), 'lines' => $entry->relationLoaded('lines') ? $entry->lines->map(fn ($l) => ['id' => $l->id, 'code' => $l->account->code, 'name' => $l->account->name, 'description' => $l->description, 'debit' => $l->debit, 'credit' => $l->credit])->all() : []];
        });
        $balanceFrom = FiscalYear::where('year', $f['year'])->first()?->starts_on->toDateString() ?? $f['year'].'-01-01';
        $allAccounts = $workspace->accounts($f['from'], $f['to'], $balanceFrom);
        $accounts = (clone $allAccounts)
            ->when($area === 'accounts' && ($f['search'] ?? null), fn ($q) => $q->where(fn ($q) => $q->where('code', 'like', '%'.$f['search'].'%')->orWhere('name', 'like', '%'.$f['search'].'%')))
            ->when($f['group'] ?? null, fn ($q, $g) => $q->where('code', 'like', $g.'%'))
            ->when($f['active'] ?? null, fn ($q, $s) => $q->where('is_active', $s === 'yes'))
            ->when(($f['tab'] ?? '') === 'groups', fn ($q) => $q->whereRaw('length(code) = 1'))
            ->when(($f['tab'] ?? '') === 'operating', fn ($q) => $q->whereIn('type', ['income', 'expense']))
            ->when(($f['tab'] ?? '') === 'financial', fn ($q) => $q->where('code', 'like', '5%'))
            ->orderBy('code')->paginate($f['per_page'] ?? 10, ['*'], 'accounts_page')->withQueryString()->through(fn ($a) => $workspace->accountRow($a));
        $groupRows = $workspace->lines($f['from'], $f['to'])->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->selectRaw('substr(code,1,1) code, sum(debit) debit, sum(credit) credit')->groupByRaw('substr(code,1,1)')->orderBy('code')->get()
            ->map(fn ($r) => ['code' => $r->code, 'balance' => AccountingWorkspace::subtract($r->debit, $r->credit)]);
        $ledger = null;
        if (($f['account'] ?? null) && $area === 'reports' && $view === 'ledger') {
            $account = LedgerAccount::where('code', $f['account'])->firstOrFail();
            $before = $account->lines()->whereHas('entry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '>=', $balanceFrom)->whereDate('entry_date', '<', $f['from']))->selectRaw('coalesce(sum(debit),0) debit, coalesce(sum(credit),0) credit')->first();
            $creditNature = in_array($account->type, ['liability', 'equity', 'income'], true);
            $opening = $creditNature ? AccountingWorkspace::subtract($before->credit, $before->debit) : AccountingWorkspace::subtract($before->debit, $before->credit);
            // SQL window includes every filtered movement before pagination; stable date / entry / line ordering.
            $lines = $workspace->lines($f['from'], $f['to'])->where('ledger_account_id', $account->id)->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->select('journal_lines.id', 'journal_lines.description', 'journal_lines.debit', 'journal_lines.credit', 'journal_entries.number', 'journal_entries.entry_date', 'journal_entries.id as entry_id')
                ->selectRaw('sum('.($creditNature ? 'credit-debit' : 'debit-credit').') over (order by entry_date, journal_entries.id, journal_lines.id) as running')
                ->orderBy('entry_date')->orderBy('journal_entries.id')->orderBy('journal_lines.id')->paginate($f['per_page'] ?? 10)->withQueryString()
                ->through(fn ($l) => [...$l->getAttributes(), 'running' => (string) BigDecimal::of($opening)->plus(AccountingWorkspace::decimal($l->running))]);
            $ledger = ['account' => $account->only(['code', 'name', 'type']), 'opening' => $opening, 'lines' => $lines];
        }

        return Inertia::render('accounting/workspace', [
            'area' => $area, 'view' => $view, 'filters' => $f, 'company' => CompanyProfile::first()?->only(['legal_name', 'tax_id']),
            'fiscalYear' => FiscalYear::where('year', $f['year'])->first()?->only(['id', 'year', 'status']),
            'summary' => $workspace->summary($f['from'], $f['to'], $balanceFrom), 'series' => $workspace->series($f['from'], $f['to']),
            'entries' => $pagedEntries, 'accounts' => $accounts, 'groups' => $groupRows, 'ledger' => $ledger,
            'reportAccounts' => $area === 'reports' && in_array($view, ['balance', 'profit', 'trial'], true) ? (clone $allAccounts)->whereHas('lines', fn ($q) => $q->whereHas('entry', fn ($e) => $e->where('status', 'posted')->whereDate('entry_date', '>=', $balanceFrom)->whereDate('entry_date', '<=', $f['to'])))->orderBy('code')->get()->map(fn ($a) => $workspace->accountRow($a)) : [],
            'capabilities' => ['manage' => Gate::allows('manage-accounting'), 'accounts' => Gate::allows('manage-staff'), 'books' => false, 'multiCompany' => false, 'statutoryMapping' => false, 'import' => false],
            'kinds' => JournalEntry::whereDate('entry_date', '>=', $f['from'])->whereDate('entry_date', '<=', $f['to'])->distinct()->orderBy('entry_kind')->pluck('entry_kind'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->filters($request);

        return Inertia::render('accounting/entry', ['entry' => null, 'canManage' => true, 'postingAccounts' => LedgerAccount::where('is_active', true)->where('is_postable', true)->orderBy('code')->get(['code', 'name'])]);
    }

    public function account(Request $request, LedgerAccount $account, AccountingWorkspace $workspace): Response
    {
        $f = $this->filters($request);
        $row = $workspace->accounts($f['from'], $f['to'])->findOrFail($account->id);

        return Inertia::render('accounting/account', ['account' => $workspace->accountRow($row), 'year' => $f['year'], 'canManage' => Gate::allows('manage-staff')]);
    }

    public function show(Request $request, JournalEntry $entry, AccountingWorkspace $workspace): Response
    {
        $this->filters($request);
        $entry = $workspace->entryQuery()->with(['lines.account:id,code,name,type', 'reversal:id,reverses_entry_id', 'reversedEntry:id,number'])->findOrFail($entry->id);
        $creator = $entry->created_by ? User::find($entry->created_by)?->name : null;
        $history = AuditLog::where('auditable_type', $entry->getMorphClass())->where('auditable_id', $entry->id)->latest('id')->limit(30)->get(['id', 'event', 'user_id', 'created_at']);

        return Inertia::render('accounting/entry', ['entry' => [...$workspace->entryRow($entry), 'lines' => $entry->lines->map(fn ($l) => ['id' => $l->id, 'code' => $l->account->code, 'name' => $l->account->name, 'description' => $l->description, 'debit' => $l->debit, 'credit' => $l->credit]), 'creator' => $creator,
            'source' => $this->source($entry), 'original' => $entry->reversedEntry?->only(['id', 'number']), 'reversal' => $entry->reversal?->only(['id']),
            'fiscalYear' => $entry->fiscalYear?->only(['year', 'status']), 'period' => AccountingPeriod::where('year', $entry->entry_date->year)->where('month', $entry->entry_date->month)->first()?->only(['year', 'month', 'status']), 'history' => $history], 'canManage' => Gate::allows('manage-accounting'),
            'postingAccounts' => LedgerAccount::where('is_active', true)->where('is_postable', true)->orderBy('code')->get(['code', 'name'])]);
    }

    private function source(JournalEntry $entry): ?array
    {
        if (! $entry->source_type || ! $entry->source_id) {
            return null;
        }
        $paths = [SalesDocument::class => '/sales/', PurchaseDocument::class => '/purchases/', Expense::class => '/expenses/', SupplierPayment::class => null];
        $class = $entry->source_type;
        $source = $entry->source;

        return ['type' => class_basename($class), 'id' => $entry->source_id, 'number' => $source?->number ?? (string) $entry->source_id, 'href' => isset($paths[$class]) ? $paths[$class].$entry->source_id : ($source instanceof SalesDocumentPayment && $source->sales_document_id ? '/sales/'.$source->sales_document_id : ($source instanceof SupplierPayment ? ($source->expense_id ? '/expenses/'.$source->expense_id : ($source->purchase_document_id ? '/purchases/'.$source->purchase_document_id : null)) : null))];
    }

    public function export(Request $request, AccountingWorkspace $workspace): StreamedResponse
    {
        $f = $this->filters($request);

        return response()->streamDownload(function () use ($workspace, $f) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Ejercicio', 'Desde', 'Hasta', 'Fecha', 'Asiento', 'Concepto', 'Cuenta', 'Descripción', 'Debe', 'Haber'], ';', '"', '');
            $workspace->lines($f['from'], $f['to'])->whereHas('account', fn ($q) => $q->when($f['account'] ?? null, fn ($q, $a) => $q->where('code', $a)))
                ->whereHas('entry', fn ($q) => $q
                    ->when($f['kind'] ?? null, fn ($q, $kind) => $q->where('entry_kind', $kind))
                    ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', "%$s%")->orWhere('description', 'like', "%$s%"))))
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')->select('journal_lines.*')->with(['account:id,code,name', 'entry:id,entry_date,number,description'])
                ->orderBy('journal_entries.entry_date')->orderBy('journal_entries.id')->orderBy('journal_lines.id')->chunk(500, function ($lines) use ($out, $f) {
                    foreach ($lines as $l) {
                        $safe = fn ($v) => preg_match('/^[=+@\-\t\r]/', (string) $v) ? "'".$v : $v;
                        fputcsv($out, array_map($safe, [$f['year'], $f['from'], $f['to'], $l->entry->entry_date->toDateString(), $l->entry->number, $l->entry->description, $l->account->code, $l->description, $l->debit, $l->credit]), ';', '"', '');
                    }
                });
            fclose($out);
        }, 'apuntes-contabilizados-'.$f['year'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
