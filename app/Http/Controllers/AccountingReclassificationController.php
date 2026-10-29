<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\AccountingReclassificationBatch;
use App\Models\BankReconciliation;
use App\Models\BankTransactionPosting;
use App\Models\BankTransfer;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TaxReturn;
use App\Models\TaxReturnPayment;
use App\Models\User;
use App\Services\Accounting\AccountingReclassification;
use App\Services\Accounting\JournalCorrection;
use App\Services\Accounting\PartyLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AccountingReclassificationController extends Controller
{
    public function party(Request $r, string $kind, int $id, PartyLedger $ledger)
    {
        $summary = $ledger->summary($kind, $id, $r);
        $f = $summary['period'];
        $d = $r->validate(['account' => 'nullable|string|max:20|exists:ledger_accounts,code', 'unattributed' => 'nullable|boolean']);
        $q = $ledger->lines($kind, $id)->whereBetween('e.entry_date', [$f['from'], $f['to']]);
        if ($r->boolean('unattributed')) {
            $q = $ledger->lines()->whereNull('p.entry_id')->whereIn('a.code', $summary['pending_accounts'])->whereBetween('e.entry_date', [$f['from'], $f['to']]);
        }
        $q->where(fn ($q) => $q->where('a.code', 'like', $kind === 'customer' ? '430%' : '400%')->orWhere('a.code', 'like', $kind === 'customer' ? '438%' : '410%')->when($kind === 'supplier', fn ($q) => $q->orWhere('a.code', 'like', '407%')))->when($d['account'] ?? null, fn ($q, $code) => $q->where('a.code', $code));
        $lines = $q->select('l.id', 'l.journal_entry_id', 'l.description', 'l.debit', 'l.credit', 'e.number', 'e.entry_date', 'e.entry_kind', 'a.code', 'a.name')->orderBy('e.entry_date')->orderBy('l.id')->paginate(25)->withQueryString();

        return Inertia::render('accounting/party-ledger', ['summary' => $summary, 'lines' => $lines, 'unattributed' => $r->boolean('unattributed')]);
    }

    public function index(Request $r, PartyLedger $ledger, JournalCorrection $corrections)
    {
        $d = $r->validate(['document' => 'nullable|string|max:150', 'party_search' => 'nullable|string|max:100', 'account' => 'nullable|string|max:20', 'proposed' => 'nullable|string|max:20', 'party_kind' => ['nullable', Rule::in(['customer', 'supplier'])], 'party_id' => 'nullable|integer|min:1', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'year' => 'nullable|integer|between:2020,2100', 'search' => 'nullable|string|max:150', 'kind' => 'nullable|string|max:64', 'status' => 'nullable|in:posted,draft']);
        abort_if(isset($d['party_id']) && ! isset($d['party_kind']), 422);
        if (isset($d['party_id'])) {
            $ledger->party($d['party_kind'], $d['party_id']);
        }
        $q = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')->leftJoinSub($ledger->evidence(), 'p', 'p.entry_id', '=', 'e.id');
        // Derive current assignments from immutable correction evidence, without relabelling actual ledger rows.
        $json = DB::getDriverName() === 'pgsql' ? "SELECT c.journal_entry_id, (x.value->>'id')::bigint line_id, x.value->>'account_code' code, row_number() over(partition by c.journal_entry_id,(x.value->>'id')::bigint order by c.id desc) position FROM accounting_entry_corrections c CROSS JOIN LATERAL jsonb_array_elements(c.line_changes::jsonb) x(value)" : "SELECT c.journal_entry_id,json_extract(x.value,'$.id') line_id,json_extract(x.value,'$.account_code') code,row_number() over(partition by c.journal_entry_id,json_extract(x.value,'$.id') order by c.id desc) position FROM accounting_entry_corrections c,json_each(c.line_changes) x";
        $q->leftJoinSub(DB::query()->fromRaw('('.$json.') ranked')->where('position', 1), 'effective_assignment', fn ($j) => $j->on('effective_assignment.line_id', '=', 'l.id')->on('effective_assignment.journal_entry_id', '=', 'e.id'));
        $q->when($d['account'] ?? null, fn ($q, $v) => $q->whereRaw('COALESCE(effective_assignment.code,a.code)=?', [$v]))->when($d['party_id'] ?? null, fn ($q, $id) => $q->where('p.party_kind', $d['party_kind'])->where('p.party_id', $id))->when($d['from'] ?? null, fn ($q, $v) => $q->whereDate('e.entry_date', '>=', $v))->when($d['to'] ?? null, fn ($q, $v) => $q->whereDate('e.entry_date', '<=', $v))->when($d['year'] ?? null, fn ($q, $v) => $q->whereYear('e.entry_date', $v))->when($d['kind'] ?? null, fn ($q, $v) => $q->where('e.entry_kind', $v))->where('e.status', $d['status'] ?? 'posted')->when($d['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('e.description', 'like', "%$v%")->orWhere('e.number', 'like', "%$v%")->orWhere('l.description', 'like', "%$v%")));
        if (! empty($d['document'])) {
            $term = $d['document'];
            $q->whereIn('e.id', JournalEntry::whereHasMorph('source', [SalesDocument::class, PurchaseDocument::class, Expense::class], function ($s, $type) use ($term) {
                $s->where('number', 'like', "%$term%");
                if ($type === PurchaseDocument::class) {
                    $s->orWhere('supplier_number', 'like', "%$term%");
                }if ($type === Expense::class) {
                    $s->orWhere('document_number', 'like', "%$term%");
                }
            })->select('id'));
        }
        $lines = $q->select('l.id', 'l.journal_entry_id', 'l.description', 'l.debit', 'l.credit', 'e.number', 'e.entry_date', 'e.entry_kind', 'e.status', 'p.party_kind', 'p.party_id')->selectRaw('COALESCE(effective_assignment.code,a.code) code')->orderByDesc('e.entry_date')->orderByDesc('l.id')->paginate(25)->withQueryString();
        $models = JournalEntry::whereIn('id', collect($lines->items())->pluck('journal_entry_id'))->with(['fiscalYear', 'reversal', 'lines.account', 'corrections'])->get()->keyBy('id');
        $models->filter(fn ($e) => ! $e->source_type || is_subclass_of($e->source_type, Model::class))->load(['source' => function (MorphTo $m) {
            $m->morphWith([SalesDocumentPayment::class => ['reconciliation'], SupplierPayment::class => ['reconciliation']]);
        }]);
        foreach ($models as $e) {
            if ($e->source_type && ! is_subclass_of($e->source_type, Model::class)) {
                $e->setRelation('source', null);
            }
        }
        $closed = AccountingPeriod::where('status', 'closed')->get(['year', 'month'])->mapWithKeys(fn ($p) => [$p->year.'-'.str_pad($p->month, 2, '0', STR_PAD_LEFT) => true]);
        $returns = TaxReturn::whereIn('status', ['prepared', 'filed'])->whereDate('period_from', '<=', ($models->max('entry_date') ?? today()))->whereDate('period_to', '>=', ($models->min('entry_date') ?? today()))->get(['period_from', 'period_to']);
        $lines->through(function ($l) use ($models, $corrections, $closed, $returns) {
            $entry = $models[$l->journal_entry_id];
            $effective = $corrections->currentLines($entry);
            $l->description = $effective[$l->id]['description'];
            $source = $entry->source;
            $date = $entry->entry_date->toDateString();
            $l->blocked_reason = null;
            if ($entry->status !== 'posted' || $entry->reversal || in_array($entry->entry_kind, ['closing', 'opening', 'correction', 'reversal', 'invoice_correction', 'expense_correction', 'closing_reversal', 'opening_reversal'], true)) {
                $l->blocked_reason = __('This journal entry requires a different protected accounting procedure.');
            } elseif ($entry->fiscalYear?->status !== 'open' || isset($closed[substr($date, 0, 7)])) {
                $l->blocked_reason = __('The correction requires open fiscal years and periods.');
            } elseif ($returns->contains(fn ($t) => substr((string) $t->period_from, 0, 10) <= $date && substr((string) $t->period_to, 0, 10) >= $date)) {
                $l->blocked_reason = __('A prepared or filed tax return protects this journal entry.');
            } elseif ($entry->source_type && ! $source) {
                $l->blocked_reason = __('The source document is unavailable. Review it before correcting this entry.');
            } elseif (preg_match('/^(57|52|46|47|129|120)/', $l->code) || $source instanceof BankReconciliation || $source instanceof BankTransactionPosting || $source instanceof BankTransfer || $source instanceof TaxReturn || $source instanceof TaxReturnPayment || (($source instanceof SalesDocumentPayment || $source instanceof SupplierPayment) && ($source->reconciliation || $source->origin_bank_transaction_id || $source->voided_at))) {
                $l->blocked_reason = __('Use the protected document, tax or treasury workflow.');
            }

            return $l;
        });
        $partyOptions = [];
        if (! empty($d['party_kind'])) {
            $parties = $d['party_kind'] === 'customer' ? Person::where('category', 'customer') : Supplier::query();
            $parties->when($d['party_search'] ?? null, function ($q, $v) use ($d) {
                $q->where(function ($q) use ($v, $d) {
                    if ($d['party_kind'] === 'customer') {
                        $q->where('first_name', 'like', "%$v%")->orWhere('last_name', 'like', "%$v%")->orWhere('company_name', 'like', "%$v%");
                    } else {
                        $q->where('legal_name', 'like', "%$v%");
                    }
                });
            });
            $partyOptions = $parties->orderBy('id')->limit(25)->get()->map(fn ($p) => ['id' => $p->id, 'name' => $d['party_kind'] === 'customer' ? ($p->company_name ?: trim($p->first_name.' '.$p->last_name)) : $p->legal_name]);
        }

        if (! empty($d['party_id']) && ! collect($partyOptions)->contains('id', (int) $d['party_id'])) {
            $chosen = $ledger->party($d['party_kind'], $d['party_id']);
            $partyOptions = collect($partyOptions)->push(['id' => $chosen->id, 'name' => $d['party_kind'] === 'customer' ? ($chosen->company_name ?: trim($chosen->first_name.' '.$chosen->last_name)) : $chosen->legal_name])->values();
        }

        return Inertia::render('accounting/reclassification', ['partyOptions' => $partyOptions, 'lines' => $lines, 'filters' => $d, 'accounts' => LedgerAccount::where('is_active', true)->where('is_postable', true)->orderBy('code')->get(['id', 'code', 'name', 'type']), 'batches' => AccountingReclassificationBatch::orderByDesc('recorded_at_utc')->orderByDesc('id')->paginate(10, ['id', 'correction_date', 'reason'], 'batches_page')->withQueryString()]);
    }

    private function data(Request $r): array
    {
        return $r->validate(['operation_key' => 'required|uuid', 'entry_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:5|max:2000', 'items' => 'required|array|min:1|max:50', 'items.*' => 'array:line_id,account_code', 'items.*.line_id' => 'required|integer|distinct', 'items.*.account_code' => 'required|string|max:20', 'preview_revision' => 'sometimes|required|string|size:64', 'confirm_partial' => 'sometimes|boolean', 'confirmed' => 'sometimes|boolean']);
    }

    public function preview(Request $r, AccountingReclassification $service)
    {
        return response()->json($service->preview($this->data($r), $r->user()));
    }

    public function store(Request $r, AccountingReclassification $service)
    {
        $r->validate(['confirmed' => 'required|accepted', 'preview_revision' => 'required|string|size:64']);
        $batch = $service->execute($this->data($r), $r->user());

        return to_route('accounting.reclassification.batch', $batch);
    }

    public function batch(AccountingReclassificationBatch $batch)
    {
        return Inertia::render('accounting/reclassification-batch', ['batch' => $batch, 'actor' => User::find($batch->created_by)?->name]);
    }
}
