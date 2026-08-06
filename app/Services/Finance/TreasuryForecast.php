<?php

namespace App\Services\Finance;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\FinancialForecastItem;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use Carbon\CarbonImmutable;

class TreasuryForecast
{
    public function __construct(private OutstandingBalanceCalculator $balances) {}

    public function build(int $year, string $scenario = 'probable'): array
    {
        $start = $year === now()->year
            ? CarbonImmutable::today()
            : CarbonImmutable::create($year, 1, 1);
        $end = $start->addWeeks(13)->subDay();
        $opening = round(
            (float) BankAccount::where('is_active', true)->sum('opening_balance')
            + (float) BankTransaction::whereDate('booked_on', '<=', $start)->sum('amount'),
            2,
        );

        $events = collect();
        SalesDocument::where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'overdue'])
            ->whereDate('due_date', '<=', $end)->get()->each(function (SalesDocument $document) use ($events, $start, $scenario) {
                $events->push(['date' => $this->effectiveDate($document->due_date, $start), 'direction' => 'in', 'amount' => $this->balances->receivable($document) * $this->collectionFactor($scenario)]);
            });
        PurchaseDocument::withSum('creditNotes as credited_total', 'amount')->where('type', 'supplier_invoice')
            ->whereIn('status', ['registered', 'pending', 'partial', 'overdue'])->whereDate('due_date', '<=', $end)->get()
            ->each(function (PurchaseDocument $document) use ($events, $start) {
                $events->push(['date' => $this->effectiveDate($document->due_date, $start), 'direction' => 'out', 'amount' => $this->balances->purchase($document)]);
            });
        Expense::whereIn('status', ['pending', 'partial', 'overdue'])->whereDate('due_date', '<=', $end)->get()
            ->each(function (Expense $expense) use ($events, $start) {
                $events->push(['date' => $this->effectiveDate($expense->due_date, $start), 'direction' => 'out', 'amount' => $this->balances->expense($expense)]);
            });
        FinancialForecastItem::where('status', 'planned')->whereDate('due_on', '<=', $end)->get()
            ->each(function (FinancialForecastItem $item) use ($events, $scenario, $start, $end) {
                foreach ($this->occurrences($item, $start, $end) as $occurrence) {
                    $events->push([
                        'date' => $occurrence,
                        'direction' => $item->direction,
                        'amount' => (float) $item->amount * $this->probabilityFactor($item->probability, $scenario),
                    ]);
                }
            });

        $running = $opening;
        $weeks = collect(range(0, 12))->map(function (int $offset) use ($start, $events, &$running) {
            $from = $start->addWeeks($offset);
            $to = $from->addDays(6);
            $week = $events->filter(fn (array $event) => $event['date']->betweenIncluded($from, $to));
            $in = round((float) $week->where('direction', 'in')->sum('amount'), 2);
            $out = round((float) $week->where('direction', 'out')->sum('amount'), 2);
            $openingBalance = $running;
            $running = round($running + $in - $out, 2);

            return [
                'label' => $from->translatedFormat('d M'),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'opening' => $openingBalance,
                'in' => $in,
                'out' => $out,
                'closing' => $running,
            ];
        });

        return [
            'scenario' => $scenario,
            'openingBalance' => $opening,
            'lowestBalance' => round((float) $weeks->min('closing'), 2),
            'closingBalance' => round((float) $weeks->last()['closing'], 2),
            'weeks' => $weeks,
        ];
    }

    private function effectiveDate(mixed $date, CarbonImmutable $start): CarbonImmutable
    {
        $value = CarbonImmutable::parse($date);

        return $value->isBefore($start) ? $start : $value;
    }

    private function collectionFactor(string $scenario): float
    {
        return match ($scenario) {
            'conservative' => 0.70,
            'optimistic' => 1.0,
            default => 0.90,
        };
    }

    private function probabilityFactor(int $probability, string $scenario): float
    {
        $base = max(0, min(100, $probability)) / 100;

        return match ($scenario) {
            'conservative' => $base >= 0.8 ? $base : 0,
            'optimistic' => min(1, $base + 0.2),
            default => $base,
        };
    }

    private function occurrences(FinancialForecastItem $item, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $date = CarbonImmutable::parse($item->due_on);
        if (! $item->recurrence) {
            return [$date->isBefore($start) ? $start : $date];
        }
        while ($date->isBefore($start)) {
            $date = $this->nextOccurrence($date, $item->recurrence);
        }
        $dates = [];
        while ($date->lessThanOrEqualTo($end)) {
            $dates[] = $date;
            $date = $this->nextOccurrence($date, $item->recurrence);
        }

        return $dates;
    }

    private function nextOccurrence(CarbonImmutable $date, string $recurrence): CarbonImmutable
    {
        return match ($recurrence) {
            'monthly' => $date->addMonthNoOverflow(),
            'quarterly' => $date->addMonthsNoOverflow(3),
            default => $date->addYearNoOverflow(),
        };
    }
}
