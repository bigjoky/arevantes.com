<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccountAssignment;
use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Attribution is per posted entry. A shared account is never evidence of ownership. */
class PartyLedger
{
    public function evidence(bool $confirmed = true): Builder
    {
        $roots = DB::table('journal_entries as e')->join('sales_documents as d', 'd.id', '=', 'e.source_id')
            ->where('e.source_type', SalesDocument::class)->whereNotNull('d.person_id')->selectRaw("e.id entry_id, 'customer' party_kind, d.person_id party_id");
        $roots->union(DB::table('journal_entries as e')->join('sales_document_payments as p', 'p.id', '=', 'e.source_id')->join('sales_documents as d', 'd.id', '=', 'p.sales_document_id')->where('e.source_type', SalesDocumentPayment::class)->whereNotNull('d.person_id')->selectRaw("e.id entry_id, 'customer' party_kind, d.person_id party_id"));
        foreach ([PurchaseDocument::class => 'purchase_documents', Expense::class => 'expenses'] as $class => $table) {
            $roots->union(DB::table('journal_entries as e')->join($table.' as d', 'd.id', '=', 'e.source_id')->where('e.source_type', $class)->whereNotNull('d.supplier_id')->selectRaw("e.id entry_id, 'supplier' party_kind, d.supplier_id party_id"));
        }
        $roots->union(DB::table('journal_entries as e')->join('supplier_payments as p', 'p.id', '=', 'e.source_id')->leftJoin('purchase_documents as d', 'd.id', '=', 'p.purchase_document_id')->leftJoin('expenses as x', 'x.id', '=', 'p.expense_id')->where('e.source_type', SupplierPayment::class)->whereRaw('coalesce(d.supplier_id,x.supplier_id) is not null')->whereRaw('(d.supplier_id is null or x.supplier_id is null or d.supplier_id=x.supplier_id)')->selectRaw("e.id entry_id, 'supplier' party_kind, coalesce(d.supplier_id,x.supplier_id) party_id"));
        // Identity edits invalidate account-only inference. Preserve all audited candidates;
        // contradictory identities remain outside the confirmed balance until resolved per entry.
        foreach ([['customer', SalesDocument::class, 'person_id'], ['supplier', PurchaseDocument::class, 'supplier_id'], ['supplier', Expense::class, 'supplier_id']] as [$kind,$class,$field]) {
            foreach (['old_values', 'new_values'] as $snapshot) {
                $old = DB::getDriverName() === 'pgsql' ? "h.old_values->>'$field'" : "json_extract(h.old_values,'$.$field')";
                $new = DB::getDriverName() === 'pgsql' ? "h.new_values->>'$field'" : "json_extract(h.new_values,'$.$field')";
                $value = $snapshot === 'old_values' ? $old : $new;
                $audit = DB::table('journal_entries as e')->join('audit_logs as h', function ($j) use ($class) {
                    $j->on('h.auditable_id', '=', 'e.source_id')->where('h.auditable_type', $class);
                })->where('e.source_type', $class)->whereIn('h.event', ['issued_invoice_corrected', 'expense_corrected'])->whereRaw("coalesce($old,'0')<>coalesce($new,'0')")->selectRaw("e.id entry_id, '$kind' party_kind, CAST(coalesce($value,'0') AS BIGINT) party_id");
                $roots->union($audit);
                if ($class === SalesDocument::class) {
                    $roots->union(DB::table('journal_entries as e')->join('sales_document_payments as payment', 'payment.id', '=', 'e.source_id')->join('audit_logs as h', fn ($j) => $j->on('h.auditable_id', '=', 'payment.sales_document_id')->where('h.auditable_type', $class))->where('e.source_type', SalesDocumentPayment::class)->where('h.event', 'issued_invoice_corrected')->whereRaw("coalesce($old,'0')<>coalesce($new,'0')")->selectRaw("e.id entry_id, '$kind' party_kind, CAST(coalesce($value,'0') AS BIGINT) party_id"));
                } else {
                    $fk = $class === Expense::class ? 'expense_id' : 'purchase_document_id';
                    $roots->union(DB::table('journal_entries as e')->join('supplier_payments as payment', 'payment.id', '=', 'e.source_id')->join('audit_logs as h', fn ($j) => $j->on('h.auditable_id', '=', 'payment.'.$fk)->where('h.auditable_type', $class))->where('e.source_type', SupplierPayment::class)->where('h.event', 'expense_corrected')->whereRaw("coalesce($old,'0')<>coalesce($new,'0')")->selectRaw("e.id entry_id, '$kind' party_kind, CAST(coalesce($value,'0') AS BIGINT) party_id"));
                }
            }
        }
        $sql = 'WITH RECURSIVE attribution(entry_id,party_kind,party_id) AS ('.$roots->toSql().' UNION SELECT child.id,a.party_kind,a.party_id FROM attribution a JOIN (SELECT id,source_id parent_id FROM journal_entries WHERE source_type=? UNION SELECT id,reverses_entry_id parent_id FROM journal_entries WHERE reverses_entry_id IS NOT NULL) child ON child.parent_id=a.entry_id) '.($confirmed ? 'SELECT entry_id,min(party_kind) party_kind,min(party_id) party_id FROM attribution GROUP BY entry_id HAVING count(*)=1 AND min(party_id)>0' : 'SELECT DISTINCT entry_id,party_kind,party_id FROM attribution');

        return DB::query()->fromRaw('('.$sql.') as evidence', [...$roots->getBindings(), JournalEntry::class]);
    }

    public function party(string $kind, int $id): Person|Supplier
    {
        abort_unless(in_array($kind, ['customer', 'supplier'], true), 404);

        return $kind === 'customer' ? Person::where('category', 'customer')->findOrFail($id) : Supplier::findOrFail($id);
    }

    public function lines(?string $kind = null, ?int $id = null): Builder
    {
        $q = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')->leftJoinSub($this->evidence(), 'p', 'p.entry_id', '=', 'e.id')->where('e.status', 'posted');
        if ($kind !== null) {
            $q->where('p.party_kind', $kind)->where('p.party_id', $id);
        }

        return $q;
    }

    private function controls(Builder $q, string $kind): Builder
    {
        return $q->where(function ($q) use ($kind) {
            foreach ($kind === 'customer' ? ['430', '438'] : ['400', '410', '407'] as $prefix) {
                $q->orWhere('a.code', 'like', $prefix.'%');
            }
        });
    }

    public function period(Request $r): array
    {
        $d = $r->validate(['ledger_year' => 'nullable|integer|between:2020,2100', 'ledger_from' => 'nullable|date_format:Y-m-d', 'ledger_to' => 'nullable|date_format:Y-m-d|after_or_equal:ledger_from']);
        $year = (int) ($d['ledger_year'] ?? today()->year);
        $fy = FiscalYear::where('year', $year)->first();
        $start = $fy?->starts_on->toDateString() ?? "$year-01-01";
        $end = $fy?->ends_on->toDateString() ?? "$year-12-31";
        $from = $d['ledger_from'] ?? $start;
        $to = $d['ledger_to'] ?? $end;
        abort_unless($from >= $start && $to <= $end && $from <= $to, 422);

        return compact('year', 'from', 'to');
    }

    public function summary(string $kind, int $id, Request $r): array
    {
        abort_unless($r->user()?->can('view-finance'), 403);
        abort_if(CompanyProfile::count() > 1, 409, __('Accounting company separation is not configured.'));
        $party = $this->party($kind, $id);
        $period = $this->period($r);
        extract($period);
        $query = $this->controls($this->lines($kind, $id), $kind)->whereDate('e.entry_date', '<=', $to);
        $accounts = (clone $query)->select('a.id', 'a.code', 'a.name', 'a.type')->selectRaw('COALESCE(SUM(CASE WHEN e.entry_date < ? THEN l.debit-l.credit ELSE 0 END),0) opening_net', [$from])->selectRaw('COALESCE(SUM(CASE WHEN e.entry_date >= ? THEN l.debit ELSE 0 END),0) debit', [$from])->selectRaw('COALESCE(SUM(CASE WHEN e.entry_date >= ? THEN l.credit ELSE 0 END),0) credit', [$from])->selectRaw('COALESCE(SUM(l.debit-l.credit),0) closing_net')->groupBy('a.id', 'a.code', 'a.name', 'a.type')->orderBy('a.code')->get();
        $current = $kind === 'customer' ? $party->receivable_account_code : $party->payable_account_code;
        $linked = $accounts->pluck('code')->merge(array_filter([$current, $kind === 'supplier' ? $party->service_payable_account_code : null]))->merge(AccountingAccountAssignment::where('entity_type', $party->getMorphClass())->where('entity_id', $id)->whereIn('role', $kind === 'customer' ? ['receivable', 'customer_advance'] : ['supplier', 'creditor', 'supplier_advance'])->join('ledger_accounts', 'ledger_accounts.id', '=', 'accounting_account_assignments.ledger_account_id')->pluck('code'))->unique();
        $rows = $accounts->map(function ($a) use ($kind, $id) {
            $sign = AccountingAccountPolicy::nature(new LedgerAccount(['code' => $a->code, 'type' => $a->type])) === 'liability' ? -1 : 1;

            return ['code' => $a->code, 'name' => $a->name, 'opening' => (string) BigDecimal::of((string) $a->opening_net)->multipliedBy($sign)->toScale(2, RoundingMode::HALF_UP), 'debit' => (string) BigDecimal::of((string) $a->debit)->toScale(2, RoundingMode::HALF_UP), 'credit' => (string) BigDecimal::of((string) $a->credit)->toScale(2, RoundingMode::HALF_UP), 'closing' => (string) BigDecimal::of((string) $a->closing_net)->multipliedBy($sign)->toScale(2, RoundingMode::HALF_UP), 'advance' => str_starts_with($a->code, $kind === 'customer' ? '438' : '407'), 'evidence' => 'posted_document', 'href' => "/accounting/parties/$kind/$id?account=".$a->code];
        });
        $candidateCodes = $this->controls(DB::table('journal_lines as l')->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->joinSub($this->evidence(false), 'candidate', 'candidate.entry_id', '=', 'e.id')->where('candidate.party_kind', $kind)->where('candidate.party_id', $id)->where('e.status', 'posted')->whereDate('e.entry_date', '<=', $to), $kind)->distinct()->pluck('a.code');
        $linked = $linked->merge($candidateCodes)->unique();
        $pending = $this->controls($this->lines(), $kind)->whereNull('p.entry_id')->whereIn('a.code', $linked)->whereDate('e.entry_date', '<=', $to);
        $sum = fn ($field, $advance) => $rows->where('advance', $advance)->reduce(fn ($sum, $a) => (string) BigDecimal::of($sum)->plus($a[$field])->toScale(2, RoundingMode::HALF_UP), '0.00');

        return ['consolidated_opening' => (string) BigDecimal::of($sum('opening', false))->minus($sum('opening', true))->toScale(2), 'consolidated_closing' => (string) BigDecimal::of($sum('closing', false))->minus($sum('closing', true))->toScale(2), 'total_debit' => (string) BigDecimal::of($sum('debit', false))->plus($sum('debit', true))->toScale(2), 'total_credit' => (string) BigDecimal::of($sum('credit', false))->plus($sum('credit', true))->toScale(2), 'period' => $period, 'party' => ['kind' => $kind, 'id' => $id, 'name' => $kind === 'supplier' ? $party->legal_name : trim(($party->company_name ?: '').' '.$party->first_name.' '.$party->last_name)], 'current_account' => $current, 'historical_accounts' => $accounts->pluck('code')->filter(fn ($v) => $v !== $current)->count(), 'accounts' => $rows->values(), 'closing' => $sum('closing', false), 'advance' => $sum('closing', true), 'opening' => $sum('opening', false), 'debit' => $sum('debit', false), 'credit' => $sum('credit', false), 'pending_accounts' => $linked->values(), 'unattributed_count' => (clone $pending)->count(), 'href' => "/accounting/parties/$kind/$id"];
    }
}
