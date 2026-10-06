<?php

namespace App\Services\Accounting;

use App\Models\BankTransaction;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class FinancialDashboardSummary
{
    /** Read-only summary; never synchronizes or posts documents on page load. */
    public function forYear(int $year): array
    {
        Gate::authorize('view-finance');
        $today = CarbonImmutable::today();
        $from = CarbonImmutable::create($year, 1, 1);
        $to = $from->endOfYear()->min($today);
        $previousTo = $to->subYearNoOverflow();
        $monthly = array_fill(1, 12, ['income' => 0.0, 'expenses' => 0.0, 'result' => 0.0]);
        $current = ['income' => 0.0, 'expenses' => 0.0];
        $previous = $current;
        $balances = ['treasury' => 0.0, 'customers' => 0.0, 'suppliers' => 0.0, 'vat' => 0.0];
        $rows = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->whereBetween('journal_entries.entry_date', [$from->subYear()->toDateString(), $to->endOfDay()->toDateTimeString()])
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_kind', '!=', 'closing')
            ->selectRaw('ledger_accounts.code, ledger_accounts.type, journal_entries.entry_date, sum(journal_lines.debit) debit, sum(journal_lines.credit) credit')
            ->groupBy('ledger_accounts.code', 'ledger_accounts.type', 'journal_entries.entry_date')
            ->get();

        foreach ($rows as $row) {
            $date = CarbonImmutable::parse($row->entry_date);
            $delta = (float) $row->debit - (float) $row->credit;
            if ($date->year === $year) {
                if (in_array($row->type, ['income', 'expense'], true)) {
                    $value = $row->type === 'income' ? -$delta : $delta;
                    $current[$row->type === 'income' ? 'income' : 'expenses'] += $value;
                    $monthly[$date->month][$row->type === 'income' ? 'income' : 'expenses'] += $value;
                }
                foreach (['treasury' => ['57'], 'customers' => ['430'], 'suppliers' => ['400', '410'], 'vat' => ['472', '477', '4700', '4750']] as $key => $prefixes) {
                    foreach ($prefixes as $prefix) {
                        if (str_starts_with($row->code, $prefix)) {
                            $balances[$key] += $delta;
                            break;
                        }
                    }
                }
            } elseif ($date->year === $year - 1 && $date->lte($previousTo) && in_array($row->type, ['income', 'expense'], true)) {
                $previous[$row->type === 'income' ? 'income' : 'expenses'] += $row->type === 'income' ? -$delta : $delta;
            }
        }
        $current['result'] = $current['income'] - $current['expenses'];
        $previous['result'] = $previous['income'] - $previous['expenses'];
        $comparison = [];
        foreach ($current as $key => $value) {
            $comparison[$key] = $year <= $today->year && abs($previous[$key]) >= 0.01
                ? round(($value - $previous[$key]) / abs($previous[$key]) * 100, 1) : null;
        }
        $entries = JournalEntry::query()->whereBetween('entry_date', [$from->toDateString(), $to->endOfDay()->toDateTimeString()]);
        $latestEntries = (clone $entries)->withSum('lines as debit', 'debit')->withSum('lines as credit', 'credit')
            ->latest('entry_date')->latest('id')->limit(5)->get()->map(fn ($entry) => [
                'id' => $entry->id, 'number' => $entry->number, 'date' => $entry->entry_date->toDateString(),
                'description' => $entry->description, 'debit' => (float) $entry->debit, 'credit' => (float) $entry->credit,
                'status' => $entry->status, 'href' => '/accounting?'.http_build_query(['year' => $year, 'view' => 'journal', 'entry' => $entry->id]),
            ])->values();
        $documents = collect();
        if (Gate::allows('manage-installations')) {
            $documents = $documents->merge(SalesDocument::query()->with('customer:id,first_name,last_name,company_name')
                ->whereIn('type', ['invoice', 'credit_note'])->whereBetween('issue_date', [$from->toDateString(), $to->endOfDay()->toDateTimeString()])
                ->latest('issue_date')->latest('id')->limit(5)->get()->map(fn ($document) => [
                    'id' => 'sales-'.$document->id, 'date' => $document->issue_date->toDateString(), 'type' => $document->type,
                    'number' => $document->number, 'party' => $document->customer?->company_name ?: trim(($document->customer?->first_name ?? '').' '.($document->customer?->last_name ?? '')),
                    'amount' => (float) $document->amount, 'status' => $document->status, 'href' => '/sales/'.$document->id,
                ]));
        }
        if (Gate::allows('view-purchases')) {
            $documents = $documents->merge(PurchaseDocument::query()->with('supplier:id,legal_name')
                ->whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->whereBetween('issue_date', [$from->toDateString(), $to->endOfDay()->toDateTimeString()])
                ->latest('issue_date')->latest('id')->limit(5)->get()->map(fn ($document) => [
                    'id' => 'purchase-'.$document->id, 'date' => $document->issue_date->toDateString(), 'type' => $document->type,
                    'number' => $document->number, 'party' => $document->supplier_snapshot['legal_name'] ?? $document->supplier?->legal_name,
                    'amount' => (float) $document->amount, 'status' => $document->status, 'href' => '/purchases/'.$document->id,
                ]));
        }
        $activity = (clone $entries)->where('status', 'posted')->whereNotNull('posted_at')->latest('posted_at')->latest('id')->limit(5)
            ->get(['id', 'number', 'posted_at'])->map(fn ($entry) => [
                'id' => 'entry-'.$entry->id, 'description' => __('Asiento contabilizado'), 'reference' => $entry->number,
                'occurred_at' => $entry->posted_at->toIso8601String(), 'href' => '/accounting?'.http_build_query(['year' => $year, 'view' => 'journal', 'entry' => $entry->id]),
            ])->values();
        $pendingBank = BankTransaction::where('status', 'pending')->whereBetween('booked_on', [$from->toDateString(), $to->endOfDay()->toDateTimeString()])->count();

        return [
            'year' => $year, 'as_of' => $to->toDateString(), 'today' => $today->toDateString(),
            'metrics' => array_map(fn ($value) => round($value, 2), $current), 'comparison' => $comparison,
            'vat_payable' => null,
            'monthly' => collect($monthly)->map(fn ($row, $month) => [
                'month' => $month, 'income' => $month <= ($year < $today->year ? 12 : ($year === $today->year ? $today->month : 0)) ? round($row['income'], 2) : null,
                'expenses' => $month <= ($year < $today->year ? 12 : ($year === $today->year ? $today->month : 0)) ? round($row['expenses'], 2) : null,
                'result' => $month <= ($year < $today->year ? 12 : ($year === $today->year ? $today->month : 0)) ? round($row['income'] - $row['expenses'], 2) : null,
            ])->values(),
            'balances' => array_map(fn ($value) => round($value, 2), $balances),
            'fiscal_status' => FiscalYear::where('year', $year)->value('status'),
            'elapsed_months' => $year < $today->year ? 12 : ($year === $today->year ? $today->month : 0),
            'posted_entries' => (clone $entries)->where('status', 'posted')->count(),
            'pending_entries' => (clone $entries)->where('status', 'draft')->count(),
            'pending_documents' => null, 'entries' => $latestEntries,
            'documents_available' => Gate::allows('manage-installations') || Gate::allows('view-purchases'),
            'documents' => $documents->sortByDesc(fn ($document) => $document['date'].' '.$document['id'])->take(5)->values(),
            'tasks' => $pendingBank ? [['id' => 'bank', 'title' => __('Conciliación bancaria'), 'count' => $pendingBank, 'due_on' => null, 'href' => '/banking']] : [],
            'activity' => $activity,
        ];
    }
}
