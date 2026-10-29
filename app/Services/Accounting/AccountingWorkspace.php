<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;

/** Read-only projections of posted journals, not a statutory financial-statement mapping. */
class AccountingWorkspace
{
    public static function decimal(mixed $value): string
    {
        return (string) BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HALF_UP);
    }

    public static function subtract(mixed $a, mixed $b): string
    {
        return (string) BigDecimal::of(self::decimal($a))->minus(self::decimal($b));
    }

    public function lines(string $from, string $to): Builder
    {
        return JournalLine::query()->whereHas('entry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to));
    }

    public function accounts(string $from, string $to, ?string $openingFrom = null)
    {
        $openingFrom ??= $from;

        return LedgerAccount::query()
            ->withSum(['lines as opening_debit' => fn ($q) => $q->whereHas('entry', fn ($e) => $e->where('status', 'posted')->whereDate('entry_date', '>=', $openingFrom)->whereDate('entry_date', '<', $from))], 'debit')
            ->withSum(['lines as opening_credit' => fn ($q) => $q->whereHas('entry', fn ($e) => $e->where('status', 'posted')->whereDate('entry_date', '>=', $openingFrom)->whereDate('entry_date', '<', $from))], 'credit')
            ->withSum(['lines as debit' => fn ($q) => $q->whereHas('entry', fn ($e) => $e->where('status', 'posted')->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to))], 'debit')
            ->withSum(['lines as credit' => fn ($q) => $q->whereHas('entry', fn ($e) => $e->where('status', 'posted')->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to))], 'credit');
    }

    public function accountRow(LedgerAccount $account): array
    {
        $debit = self::decimal($account->debit);
        $credit = self::decimal($account->credit);

        $opening = self::subtract($account->opening_debit, $account->opening_credit);
        $net = (string) BigDecimal::of($opening)->plus($debit)->minus($credit);

        return [...$account->only(['id', 'code', 'name', 'type', 'parent_code', 'level', 'is_active', 'is_postable', 'source', 'source_version']), 'debit' => $debit, 'credit' => $credit, 'opening' => $opening, 'net' => $net,
            'stored_type' => $account->type, 'type' => AccountingAccountPolicy::nature($account), 'effective_type' => AccountingAccountPolicy::nature($account), 'balance' => in_array(AccountingAccountPolicy::nature($account), ['liability', 'equity', 'income'], true) ? (string) BigDecimal::of($net)->negated() : $net];
    }

    public function summary(string $from, string $to, ?string $balanceFrom = null): array
    {
        $rows = $this->lines($balanceFrom ?? $from, $to)->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->selectRaw(AccountingAccountPolicy::natureExpression().' as type, coalesce(sum(debit),0) debit, coalesce(sum(credit),0) credit')->groupByRaw(AccountingAccountPolicy::natureExpression())->get()->keyBy('type');
        $balances = [];
        foreach (['asset', 'liability', 'equity', 'income', 'expense'] as $type) {
            $debit = $rows[$type]->debit ?? '0';
            $credit = $rows[$type]->credit ?? '0';
            $balances[$type] = in_array($type, ['asset', 'expense'], true) ? self::subtract($debit, $credit) : self::subtract($credit, $debit);
        }
        $unclosed = self::subtract($balances['income'], $balances['expense']);
        $difference = self::subtract(self::subtract(self::subtract($balances['asset'], $balances['liability']), $balances['equity']), $unclosed);
        // Closing entries transfer the result into equity. Exclude them only from P&L, not the balance equation.
        $profitRows = $this->lines($from, $to)->whereHas('entry', fn ($q) => $q->whereNotIn('entry_kind', ['closing', 'closing_reversal']))
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')->whereIn('ledger_accounts.type', ['income', 'expense'])
            ->selectRaw('ledger_accounts.type, coalesce(sum(debit),0) debit, coalesce(sum(credit),0) credit')->groupBy('ledger_accounts.type')->get()->keyBy('type');
        $income = self::subtract($profitRows['income']->credit ?? 0, $profitRows['income']->debit ?? 0);
        $expenses = self::subtract($profitRows['expense']->debit ?? 0, $profitRows['expense']->credit ?? 0);

        return ['assets' => $balances['asset'], 'liabilities' => $balances['liability'], 'equity' => $balances['equity'], 'unclosedResult' => $unclosed,
            'income' => $income, 'expenses' => $expenses, 'result' => self::subtract($income, $expenses), 'difference' => $difference, 'equityAndLiabilities' => (string) BigDecimal::of($balances['equity'])->plus($balances['liability']),
            'postedEntries' => JournalEntry::where('status', 'posted')->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to)->count(),
            'draftEntries' => JournalEntry::where('status', 'draft')->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to)->count(), 'provisional' => true];
    }

    public function series(string $from, string $to): array
    {
        return $this->lines($from, $to)->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->whereNotIn('journal_entries.entry_kind', ['closing', 'closing_reversal'])->whereIn('ledger_accounts.type', ['income', 'expense'])
            ->selectRaw('substr(cast(entry_date as text),1,7) as period_month, ledger_accounts.type, sum(debit) debit, sum(credit) credit')
            ->groupByRaw('substr(cast(entry_date as text),1,7), ledger_accounts.type')->orderBy('period_month')->get()->groupBy('period_month')->map(function ($rows, $month) {
                $r = $rows->keyBy('type');
                $income = self::subtract($r['income']->credit ?? 0, $r['income']->debit ?? 0);
                $expenses = self::subtract($r['expense']->debit ?? 0, $r['expense']->credit ?? 0);

                return ['month' => $month, 'income' => $income, 'expenses' => $expenses, 'result' => self::subtract($income, $expenses)];
            })->values()->all();
    }

    public function entryQuery(): Builder
    {
        return JournalEntry::query()->withSum('lines as debit', 'debit')->withSum('lines as credit', 'credit');
    }

    public function entryRow(JournalEntry $entry): array
    {
        return [...$entry->only(['id', 'number', 'description', 'status', 'entry_kind', 'event', 'fiscal_year_id', 'created_by', 'reverses_entry_id']),
            'annotated_description' => $entry->relationLoaded('corrections') ? $entry->corrections->whereNotNull('description')->last()?->description : null, 'source_reference' => $entry->relationLoaded('source') ? ($entry->source?->supplier_number ?? $entry->source?->document_number ?? $entry->source?->number ?? $entry->source?->reference) : null, 'creator' => $entry->relationLoaded('creator') ? $entry->creator?->name : null, 'correction_links' => $entry->relationLoaded('corrections') ? $entry->corrections->map(fn ($c) => ['id' => $c->id, 'entry_id' => $c->correction_entry_id])->all() : [],
            'entry_date' => $entry->entry_date->toDateString(), 'created_at' => $entry->created_at?->toIso8601String(), 'posted_at' => $entry->posted_at?->toIso8601String(),
            'debit' => self::decimal($entry->debit), 'credit' => self::decimal($entry->credit), 'href' => '/accounting/entries/'.$entry->id];
    }
}
