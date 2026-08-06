<?php

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\AccountingRecurringRun;
use App\Models\AccountingTemplate;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingPeriodManager
{
    public function assertOpen(string $date): void
    {
        $value = CarbonImmutable::parse($date);
        if (AccountingPeriod::where('year', $value->year)->where('month', $value->month)->where('status', 'closed')->exists()) {
            throw ValidationException::withMessages([
                'accounting_period' => __('The accounting period :period is closed.', ['period' => $value->format('Y-m')]),
            ]);
        }
    }

    /** @return array<string, int> */
    public function summary(int $year, int $month): array
    {
        $entries = JournalEntry::withSum('lines as debit_total', 'debit')
            ->withSum('lines as credit_total', 'credit')
            ->whereYear('entry_date', $year)
            ->whereMonth('entry_date', $month)
            ->get();

        return [
            'journalEntries' => $entries->count(),
            'unbalancedEntries' => $entries->filter(fn (JournalEntry $entry) => abs((float) $entry->debit_total - (float) $entry->credit_total) > 0.005)->count(),
            'unpostedItems' => $this->unpostedItems($year, $month),
            'unreconciledBankTransactions' => BankTransaction::whereYear('booked_on', $year)->whereMonth('booked_on', $month)->where('status', 'pending')->count(),
        ];
    }

    /** @param array<string, bool> $confirmations */
    public function close(int $year, int $month, int $userId, array $confirmations, ?string $notes): AccountingPeriod
    {
        $end = CarbonImmutable::create($year, $month, 1)->endOfMonth();
        if ($end->isAfter(today())) {
            throw ValidationException::withMessages(['period' => __('An accounting period cannot be closed before month end.')]);
        }
        if (in_array(false, $confirmations, true)) {
            throw ValidationException::withMessages(['checklist' => __('Complete every monthly closing confirmation.')]);
        }

        return DB::transaction(function () use ($year, $month, $userId, $confirmations, $notes) {
            $fiscalYear = FiscalYear::where('year', $year)->lockForUpdate()->firstOrFail();
            if ($fiscalYear->status !== 'open') {
                throw ValidationException::withMessages(['period' => __('The fiscal year is not open.')]);
            }
            AccountingPeriod::firstOrCreate(
                ['year' => $year, 'month' => $month],
                ['fiscal_year_id' => $fiscalYear->id, 'status' => 'open'],
            );
            $period = AccountingPeriod::where('year', $year)->where('month', $month)->lockForUpdate()->firstOrFail();
            if ($period->status === 'closed') {
                throw ValidationException::withMessages(['period' => __('The accounting period is already closed.')]);
            }

            $summary = $this->summary($year, $month);
            if ($summary['unpostedItems'] > 0 || $summary['unbalancedEntries'] > 0 || $summary['unreconciledBankTransactions'] > 0) {
                throw ValidationException::withMessages(['period' => __('Resolve all accounting and bank exceptions before closing the month.')]);
            }

            $period->update([
                'status' => 'closed',
                'checklist' => [...$summary, ...$confirmations],
                'closure_notes' => $notes,
                'closed_by' => $userId,
                'closed_at' => now(),
            ]);

            return $period;
        });
    }

    public function reopen(int $year, int $month, int $userId, string $reason): AccountingPeriod
    {
        return DB::transaction(function () use ($year, $month, $userId, $reason) {
            $period = AccountingPeriod::where('year', $year)->where('month', $month)->lockForUpdate()->firstOrFail();
            if ($period->status !== 'closed') {
                throw ValidationException::withMessages(['period' => __('Only a closed accounting period can be reopened.')]);
            }
            $period->update([
                'status' => 'open',
                'reopened_by' => $userId,
                'reopened_at' => now(),
                'reopen_reason' => $reason,
            ]);

            return $period;
        });
    }

    private function unpostedItems(int $year, int $month): int
    {
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->endOfMonth();
        $dueRecurring = AccountingTemplate::where('is_active', true)
            ->whereNotNull('recurrence')
            ->whereBetween('next_run_on', [$periodStart, $periodEnd])
            ->where(fn (Builder $query) => $query->whereNull('end_on')->orWhereColumn('next_run_on', '<=', 'end_on'))
            ->whereDoesntHave('recurringRuns', fn (Builder $query) => $query->whereColumn('scheduled_on', 'accounting_templates.next_run_on'))
            ->count();
        $failedRecurring = AccountingRecurringRun::whereNull('journal_entry_id')
            ->whereBetween('scheduled_on', [$periodStart, $periodEnd])
            ->count();

        return $dueRecurring + $failedRecurring + $this->missing(SalesDocument::query()
            ->whereIn('type', ['invoice', 'credit_note'])
            ->whereNot('status', 'draft'), 'issue_date', $year, $month, SalesDocument::class, 'document')
            + $this->missing(PurchaseDocument::query()
                ->whereIn('type', ['supplier_invoice', 'supplier_credit_note'])
                ->whereNotIn('status', ['draft', 'review_required'])
                ->where(fn (Builder $query) => $query->whereNull('matching_status')->orWhereNot('matching_status', 'review_required')), 'issue_date', $year, $month, PurchaseDocument::class, 'document')
            + $this->missing(Expense::query(), 'expense_date', $year, $month, Expense::class, 'document')
            + $this->missing(SalesDocumentPayment::query(), 'paid_at', $year, $month, SalesDocumentPayment::class, 'payment')
            + $this->missing(SupplierPayment::query(), 'paid_at', $year, $month, SupplierPayment::class, 'payment');
    }

    private function missing(Builder $query, string $dateColumn, int $year, int $month, string $modelClass, string $event): int
    {
        $source = new $modelClass;
        $postedIds = JournalEntry::where('source_type', $source->getMorphClass())->where('event', $event)->pluck('source_id');

        return $query->whereYear($dateColumn, $year)->whereMonth($dateColumn, $month)->whereNotIn('id', $postedIds)->count();
    }
}
