<?php

namespace App\Services\Accounting;

use App\Models\AccountingRecurringRun;
use App\Models\AccountingTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecurringEntryProcessor
{
    public function __construct(private JournalPoster $poster) {}

    /** @return array{posted: int, reversed: int, failed: int} */
    public function process(?CarbonImmutable $through = null, ?int $userId = null): array
    {
        $through ??= CarbonImmutable::today();
        $result = ['posted' => 0, 'reversed' => 0, 'failed' => 0];

        AccountingTemplate::with('lines')
            ->where('is_active', true)
            ->whereNotNull('recurrence')
            ->whereDate('next_run_on', '<=', $through)
            ->where(fn ($query) => $query->whereNull('end_on')->orWhereColumn('next_run_on', '<=', 'end_on'))
            ->orderBy('next_run_on')
            ->each(function (AccountingTemplate $template) use ($through, $userId, &$result) {
                $iterations = 0;
                while ($template->next_run_on && $template->next_run_on->lte($through) && (! $template->end_on || $template->next_run_on->lte($template->end_on)) && $iterations++ < 60) {
                    try {
                        $this->post($template, $userId);
                        $result['posted']++;
                    } catch (Throwable $exception) {
                        AccountingRecurringRun::firstOrCreate(
                            ['accounting_template_id' => $template->id, 'scheduled_on' => $template->next_run_on],
                            ['amount' => $template->recurring_amount, 'status' => 'failed'],
                        )->update(['status' => 'failed', 'failure_message' => $exception->getMessage()]);
                        $result['failed']++;
                        break;
                    }
                    $template->refresh();
                }
            });

        AccountingRecurringRun::with('journalEntry.lines.account')
            ->whereIn('status', ['posted', 'reversal_failed'])
            ->whereNull('reversal_entry_id')
            ->whereDate('reversal_due_on', '<=', $through)
            ->orderBy('reversal_due_on')
            ->each(function (AccountingRecurringRun $run) use ($userId, &$result) {
                try {
                    $this->reverse($run, $userId);
                    $result['reversed']++;
                } catch (Throwable $exception) {
                    $run->update(['status' => 'reversal_failed', 'failure_message' => $exception->getMessage()]);
                    $result['failed']++;
                }
            });

        return $result;
    }

    private function post(AccountingTemplate $template, ?int $userId): void
    {
        DB::transaction(function () use ($template, $userId) {
            $locked = AccountingTemplate::with('lines')->lockForUpdate()->findOrFail($template->id);
            $scheduledOn = CarbonImmutable::parse($locked->next_run_on);
            $run = AccountingRecurringRun::firstOrCreate(
                ['accounting_template_id' => $locked->id, 'scheduled_on' => $scheduledOn],
                [
                    'amount' => $locked->recurring_amount,
                    'reversal_due_on' => $locked->reversal_days ? $scheduledOn->addDays($locked->reversal_days) : null,
                    'status' => 'pending',
                ],
            );

            if (! $run->journal_entry_id) {
                $entry = $this->poster->post(
                    $scheduledOn->toDateString(),
                    $locked->description ?: $locked->name,
                    $locked->journalLines((float) $run->amount),
                    $userId,
                    $locked->reversal_days ? 'accrual' : 'recurring',
                    $run,
                    'posting',
                );
                $run->update(['journal_entry_id' => $entry->id, 'status' => 'posted', 'failure_message' => null]);
            }

            $locked->update([
                'last_run_on' => $scheduledOn,
                'next_run_on' => $this->nextDate($scheduledOn, $locked->recurrence),
            ]);
        });
    }

    private function reverse(AccountingRecurringRun $run, ?int $userId): void
    {
        DB::transaction(function () use ($run, $userId) {
            $locked = AccountingRecurringRun::with('journalEntry.lines.account')->lockForUpdate()->findOrFail($run->id);
            if ($locked->reversal_entry_id || ! $locked->journalEntry || ! $locked->reversal_due_on) {
                return;
            }
            $lines = $locked->journalEntry->lines->map(fn ($line) => [
                'account_code' => $line->account->code,
                'description' => $line->description,
                'debit' => $line->credit,
                'credit' => $line->debit,
            ])->all();
            $entry = $this->poster->post(
                $locked->reversal_due_on->toDateString(),
                __('Automatic reversal of :entry', ['entry' => $locked->journalEntry->number]),
                $lines,
                $userId,
                'reversal',
                $locked,
                'reversal',
                $locked->journalEntry,
            );
            $locked->update(['reversal_entry_id' => $entry->id, 'status' => 'reversed', 'failure_message' => null]);
        });
    }

    private function nextDate(CarbonImmutable $date, string $recurrence): CarbonImmutable
    {
        $months = match ($recurrence) {
            'monthly' => 1,
            'quarterly' => 3,
            'yearly' => 12,
        };
        $next = $date->addMonthsNoOverflow($months);

        return $date->isLastOfMonth() ? $next->endOfMonth()->startOfDay() : $next;
    }
}
