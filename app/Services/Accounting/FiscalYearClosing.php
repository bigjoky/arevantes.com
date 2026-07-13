<?php

namespace App\Services\Accounting;

use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalYearClosing
{
    public function __construct(private JournalPoster $poster, private AccountingSynchronizer $synchronizer) {}

    public function close(FiscalYear $year, int $userId): void
    {
        if ($year->status !== 'open') {
            throw ValidationException::withMessages(['year' => __('The fiscal year is already closed.')]);
        }
        $this->synchronizer->sync($userId);

        DB::transaction(function () use ($year, $userId) {
            $nominal = $this->balances($year, ['income', 'expense']);
            $lines = [];
            $resultDebit = $resultCredit = 0.0;
            foreach ($nominal as $account) {
                $net = round((float) $account->debit - (float) $account->credit, 2);
                if ($net > 0) {
                    $lines[] = $this->line($account->code, 0, $net);
                    $resultDebit += $net;
                } elseif ($net < 0) {
                    $lines[] = $this->line($account->code, abs($net), 0);
                    $resultCredit += abs($net);
                }
            }
            $result = round($resultCredit - $resultDebit, 2);
            if ($result > 0) {
                $lines[] = $this->line('129', 0, $result);
            } elseif ($result < 0) {
                $lines[] = $this->line('129', abs($result), 0);
            }
            if ($lines) {
                $this->poster->post($year->ends_on->toDateString(), __('Closing entry :year', ['year' => $year->year]), $lines, $userId, 'closing', $year, 'closing');
            }
            $year->update(['status' => 'closed', 'closed_by' => $userId, 'closed_at' => now()]);
        });
    }

    public function openNext(FiscalYear $closedYear, int $userId): JournalEntry
    {
        if ($closedYear->status !== 'closed') {
            throw ValidationException::withMessages(['year' => __('Close the previous fiscal year first.')]);
        }
        $next = FiscalYear::where('year', $closedYear->year + 1)->first();
        if (! $next || $next->status !== 'open') {
            throw ValidationException::withMessages(['year' => __('Create and open the next fiscal year first.')]);
        }
        $balances = $this->balances($closedYear, ['asset', 'liability', 'equity']);
        $lines = [];
        foreach ($balances as $account) {
            $net = round((float) $account->debit - (float) $account->credit, 2);
            if ($net > 0) {
                $lines[] = $this->line($account->code, $net, 0);
            } elseif ($net < 0) {
                $lines[] = $this->line($account->code, 0, abs($net));
            }
        }

        return $this->poster->post($next->starts_on->toDateString(), __('Opening entry from :year', ['year' => $closedYear->year]), $lines, $userId, 'opening', $closedYear, 'opening');
    }

    private function balances(FiscalYear $year, array $types)
    {
        return LedgerAccount::whereIn('type', $types)->withSum(['lines as debit' => fn ($query) => $query->whereHas('entry', fn ($entry) => $entry->where('fiscal_year_id', $year->id))], 'debit')->withSum(['lines as credit' => fn ($query) => $query->whereHas('entry', fn ($entry) => $entry->where('fiscal_year_id', $year->id))], 'credit')->get()->filter(fn ($account) => round((float) $account->debit - (float) $account->credit, 2) !== 0.0);
    }

    private function line(string $code, float $debit, float $credit): array
    {
        return ['account_code' => $code, 'debit' => $debit, 'credit' => $credit];
    }
}
