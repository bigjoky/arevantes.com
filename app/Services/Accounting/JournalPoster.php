<?php

namespace App\Services\Accounting;

use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Services\Documents\DocumentNumber;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalPoster
{
    public function __construct(private AccountingPeriodManager $periods, private DocumentNumber $numbers) {}

    public function post(string $date, string $description, array $lines, ?int $userId, string $kind = 'manual', ?Model $source = null, string $event = 'manual', ?JournalEntry $reverses = null, ?JournalEntry $draft = null): JournalEntry
    {
        $lines = $this->normalizeLines($lines);

        if (! in_array($kind, ['closing', 'closing_reversal', 'opening_reversal'], true)) {
            $this->periods->assertOpen($date);
        }

        return DB::transaction(function () use ($date, $description, $lines, $userId, $kind, $source, $event, $reverses, $draft) {
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

            if ($draft) {
                $draft = JournalEntry::whereKey($draft->id)->lockForUpdate()->firstOrFail();
                if ($draft->status !== 'draft' || $draft->source_type) {
                    throw ValidationException::withMessages(['entry' => __('Only manual drafts can be changed.')]);
                }
            }
            $attributes = [
                'fiscal_year_id' => $year->id, 'created_by' => $draft?->created_by ?? $userId, 'entry_date' => $date,
                'number' => $this->numbers->next('journal_entry', $date, 6)['number'], 'description' => $description,
                'source_type' => $source?->getMorphClass(), 'source_id' => $source?->getKey(),
                'event' => $event, 'entry_kind' => $kind, 'reverses_entry_id' => $reverses?->id,
                'status' => 'posted', 'posted_at' => now(),
            ];
            $entry = $draft ?? new JournalEntry;
            if ($draft) {
                $entry->lines()->get()->each->delete();
            }
            $entry->fill($attributes)->save();

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

    /** Normalize money exactly before any write; drafts may be incomplete but never negative/two-sided. */
    public function normalizeLines(array $lines, bool $balanced = true): array
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => __('The journal entry requires 2 to 50 lines.')]);
        }
        $debit = BigDecimal::zero()->toScale(2);
        $credit = BigDecimal::zero()->toScale(2);
        foreach ($lines as &$line) {
            foreach (['debit', 'credit'] as $side) {
                $raw = (string) ($line[$side] ?? '');
                if (! preg_match('/^\d{1,12}(?:\.\d+)?$/D', $raw)) {
                    throw ValidationException::withMessages(['lines' => __('Journal amounts must be finite, non-negative numbers.')]);
                }
                $line[$side] = (string) BigDecimal::of($raw)->toScale(2, RoundingMode::HALF_UP);
                if (BigDecimal::of($line[$side])->isGreaterThan('999999999999.99')) {
                    throw ValidationException::withMessages(['lines' => __('Journal amount exceeds the supported limit.')]);
                }
            }
            if (BigDecimal::of($line['debit'])->isPositive() && BigDecimal::of($line['credit'])->isPositive()) {
                throw ValidationException::withMessages(['lines' => __('Each journal line must contain either a debit or a credit amount.')]);
            }
            $debit = $debit->plus($line['debit']);
            $credit = $credit->plus($line['credit']);
        }
        unset($line);
        if ($balanced && (! $debit->isPositive() || ! $debit->isEqualTo($credit) || collect($lines)->contains(fn ($l) => $l['debit'] === '0.00' && $l['credit'] === '0.00'))) {
            throw ValidationException::withMessages(['lines' => __('The journal entry must be balanced and greater than zero.')]);
        }

        return $lines;
    }
}
