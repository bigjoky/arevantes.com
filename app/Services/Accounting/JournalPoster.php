<?php

namespace App\Services\Accounting;

use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Services\Documents\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalPoster
{
    public function __construct(private AccountingPeriodManager $periods, private DocumentNumber $numbers) {}

    public function post(string $date, string $description, array $lines, ?int $userId, string $kind = 'manual', ?Model $source = null, string $event = 'manual', ?JournalEntry $reverses = null): JournalEntry
    {
        foreach ($lines as &$line) {
            foreach (['debit', 'credit'] as $side) {
                if (! is_numeric($line[$side] ?? null) || ! is_finite((float) $line[$side]) || (float) $line[$side] < 0) {
                    throw ValidationException::withMessages(['lines' => __('Journal amounts must be finite, non-negative numbers.')]);
                }
                $line[$side] = round((float) $line[$side], 2);
            }
        }
        unset($line);
        $debit = round(collect($lines)->sum(fn ($line) => (float) $line['debit']), 2);
        $credit = round(collect($lines)->sum(fn ($line) => (float) $line['credit']), 2);

        if ($debit <= 0 || abs($debit - $credit) > 0.001) {
            throw ValidationException::withMessages(['lines' => __('The journal entry must be balanced and greater than zero.')]);
        }

        foreach ($lines as $line) {
            if (((float) $line['debit'] > 0) === ((float) $line['credit'] > 0)) {
                throw ValidationException::withMessages(['lines' => __('Each journal line must contain either a debit or a credit amount.')]);
            }
        }

        if (! in_array($kind, ['closing', 'closing_reversal', 'opening_reversal'], true)) {
            $this->periods->assertOpen($date);
        }

        return DB::transaction(function () use ($date, $description, $lines, $userId, $kind, $source, $event, $reverses) {
            $year = FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->lockForUpdate()->first();

            if (! $year || $year->status !== 'open') {
                throw ValidationException::withMessages(['entry_date' => __('The entry date must belong to an open fiscal year.')]);
            }
            if (! in_array($kind, ['closing', 'closing_reversal', 'opening_reversal'], true)) {
                // Monthly closure takes the same year lock: recheck inside it.
                $this->periods->assertOpen($date);
            }

            if ($source && JournalEntry::whereMorphedTo('source', $source)->where('event', $event)->exists()) {
                throw ValidationException::withMessages(['entry' => __('This accounting event has already been posted.')]);
            }

            if ($reverses && $reverses->reversal()->exists()) {
                throw ValidationException::withMessages(['entry' => __('This journal entry has already been reversed.')]);
            }

            $entry = JournalEntry::create([
                'fiscal_year_id' => $year->id, 'created_by' => $userId, 'entry_date' => $date,
                'number' => $this->numbers->next('journal_entry', $date, 6)['number'], 'description' => $description,
                'source_type' => $source?->getMorphClass(), 'source_id' => $source?->getKey(),
                'event' => $event, 'entry_kind' => $kind, 'reverses_entry_id' => $reverses?->id,
                'status' => 'posted', 'posted_at' => now(),
            ]);

            $accounts = LedgerAccount::whereIn('code', collect($lines)->pluck('account_code'))->get()->keyBy('code');
            foreach ($lines as $line) {
                $account = $accounts->get($line['account_code']);
                if (! $account?->is_active || ! $account->is_postable) {
                    throw ValidationException::withMessages(['lines' => __('An accounting account is missing, inactive or not postable.')]);
                }
                $entry->lines()->create(['ledger_account_id' => $account->id, 'description' => $line['description'] ?? null, 'debit' => $line['debit'], 'credit' => $line['credit']]);
            }

            return $entry;
        });
    }
}
