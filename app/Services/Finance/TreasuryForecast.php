<?php

namespace App\Services\Finance;

use App\Models\BankAccount;
use App\Models\BankFinancialProduct;
use App\Models\Expense;
use App\Models\FinancialForecastItem;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Services\Banking\CardSettlementSchedule;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TreasuryForecast
{
    public function __construct(
        private OutstandingBalanceCalculator $balances,
        private CardSettlementSchedule $cardSettlements,
        private PurchaseCommitmentAnalysis $commitments,
    ) {}

    /** @param Collection<int, array<string, mixed>>|null $purchaseCommitments */
    public function build(int $year, string $scenario = 'probable', ?Collection $purchaseCommitments = null, bool $exact = false): array
    {
        $start = $year === now()->year
            ? CarbonImmutable::today()
            : CarbonImmutable::create($year, 1, 1);
        $end = $start->addWeeks(13)->subDay();
        $accounts = BankAccount::where('is_active', true)
            ->withSum(['transactions as balance_movement' => fn ($query) => $query->whereDate('booked_on', '<=', $start)], 'amount')
            ->orderBy('name')
            ->get();
        $openingSources = $accounts->where('currency', 'EUR')->map(fn (BankAccount $account) => [
            'id' => $account->id,
            'name' => $account->name,
            'currency' => $account->currency,
            'balance' => $this->money(BigDecimal::of($account->opening_balance)->plus((string) ($account->balance_movement ?? 0))),
            'href' => '/banking?account='.$account->id,
        ])->values();
        $opening = $this->sum($openingSources, 'balance');
        $excludedCurrencyBalances = $accounts->where('currency', '!=', 'EUR')->groupBy('currency')->map(fn (Collection $currencyAccounts, string $currency) => [
            'currency' => $currency,
            'balance' => round((float) $currencyAccounts->sum(fn (BankAccount $account) => (float) $account->opening_balance + (float) ($account->balance_movement ?? 0)), 2),
            'accounts' => $currencyAccounts->count(),
        ])->values();

        $events = collect();
        SalesDocument::withSum(['childDocuments as credited_total' => fn ($q) => $q->where('type', 'credit_note')->whereNotIn('status', ['draft', 'cancelled'])], 'amount')->where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'overdue'])
            ->whereDate('due_date', '<=', $end)->get()->each(function (SalesDocument $document) use ($events, $start, $scenario) {
                $outstanding = $this->balances->receivableDecimal($document);
                $this->addEvent($events, $this->effectiveDate($document->due_date, $start), 'in', $outstanding, BigDecimal::of($outstanding)->multipliedBy((string) $this->collectionFactor($scenario)), 'receivable', $document->id, $document->number, '/sales/'.$document->id);
            });
        PurchaseDocument::withSum('creditNotes as credited_total', 'amount')->where('type', 'supplier_invoice')
            ->whereIn('status', ['registered', 'pending', 'partial', 'overdue'])->whereDate('due_date', '<=', $end)->get()
            ->each(function (PurchaseDocument $document) use ($events, $start) {
                $outstanding = $this->balances->net($document->amount, $document->paid_amount, (string) BigDecimal::of((string) ($document->credited_total ?? 0))->abs());
                $this->addEvent($events, $this->effectiveDate($document->due_date, $start), 'out', $outstanding, $outstanding, 'supplier_invoice', $document->id, $document->supplier_number ?: $document->number, '/purchases/'.$document->id);
            });
        Expense::where('currency_code', 'EUR')->whereIn('status', ['pending', 'partial', 'overdue'])->whereDate('due_date', '<=', $end)->get()
            ->each(function (Expense $expense) use ($events, $start) {
                $outstanding = $this->balances->net($expense->amount, $expense->paid_amount);
                $this->addEvent($events, $this->effectiveDate($expense->due_date, $start), 'out', $outstanding, $outstanding, 'expense', $expense->id, $expense->number ?: $expense->document_number ?: __('Expense #:id', ['id' => $expense->id]), '/expenses/'.$expense->id);
            });
        ($purchaseCommitments ?? $this->commitments->build($year)['rows'])
            ->filter(fn (array $commitment) => CarbonImmutable::parse($commitment['expectedPaymentOn'])->lessThanOrEqualTo($end))
            ->each(function (array $commitment) use ($events, $start) {
                $this->addEvent(
                    $events,
                    $this->effectiveDate($commitment['expectedPaymentOn'], $start),
                    'out',
                    (string) $commitment['remainingCash'],
                    (string) $commitment['remainingCash'],
                    'purchase_commitment',
                    (int) $commitment['id'],
                    __('Commitment :number', ['number' => $commitment['number']]),
                    $commitment['href'],
                );
            });
        FinancialForecastItem::where('status', 'planned')->whereDate('due_on', '<=', $end)->get()
            ->each(function (FinancialForecastItem $item) use ($events, $scenario, $start, $end) {
                foreach ($this->occurrences($item, $start, $end) as $occurrence) {
                    $this->addEvent($events, $occurrence, $item->direction, $item->amount, BigDecimal::of($item->amount)->multipliedBy((string) $this->probabilityFactor($item->probability, $scenario)), 'forecast_item', $item->id, $item->description, '#forecast-items');
                }
            });
        BankFinancialProduct::where('is_active', true)
            ->where('currency', 'EUR')
            ->where('used_amount', '>', 0)
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('type', 'credit_facility')->whereHas('linkedCards'))
                ->orWhere(fn ($query) => $query->where('type', 'credit_card')->whereNull('shared_credit_facility_id')))
            ->get()
            ->each(function (BankFinancialProduct $product) use ($events, $start, $end) {
                $paymentOn = $this->cardSettlements->next($product, $start)['paymentOn'];
                if ($paymentOn->lessThanOrEqualTo($end)) {
                    $sourceType = $product->type === 'credit_card' ? 'credit_card' : 'credit_facility';
                    $this->addEvent($events, $paymentOn, 'out', $product->used_amount, $product->used_amount, $sourceType, $product->id, $product->name, '/banking?account='.$product->bank_account_id);
                }
            });

        $dailyRunning = $opening;
        $days = collect(range(0, 90))->map(function (int $offset) use ($start, $events, &$dailyRunning) {
            $date = $start->addDays($offset);
            $dayEvents = $events
                ->filter(fn (array $event) => $event['date']->isSameDay($date))
                ->sortBy(fn (array $event) => [$event['direction'] === 'out' ? 0 : 1, $event['label']]);
            $in = $this->sum($dayEvents->where('direction', 'in'), 'amount');
            $out = $this->sum($dayEvents->where('direction', 'out'), 'amount');
            $openingBalance = $dailyRunning;
            $dailyRunning = $this->money(BigDecimal::of($dailyRunning)->plus($in)->minus($out));

            return [
                'date' => $date->toDateString(),
                'label' => $date->translatedFormat('D d M'),
                'opening' => $openingBalance,
                'in' => $in,
                'out' => $out,
                'closing' => $dailyRunning,
                'maturities' => $dayEvents->count(),
            ];
        });
        $lowestDay = $days->sortBy('closing')->first();

        $running = $opening;
        $weeks = collect(range(0, 12))->map(function (int $offset) use ($start, $events, &$running) {
            $from = $start->addWeeks($offset);
            $to = $from->addDays(6);
            $week = $events->filter(fn (array $event) => $event['date']->betweenIncluded($from, $to));
            $in = $this->sum($week->where('direction', 'in'), 'amount');
            $out = $this->sum($week->where('direction', 'out'), 'amount');
            $openingBalance = $running;
            $running = $this->money(BigDecimal::of($running)->plus($in)->minus($out));

            return [
                'label' => $from->translatedFormat('d M'),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'opening' => $openingBalance,
                'in' => $in,
                'out' => $out,
                'closing' => $running,
                'events' => $week->sortBy('date')->map(fn (array $event) => [...$event, 'date' => $event['date']->toDateString()])->values(),
            ];
        });

        $result = [
            'scenario' => $scenario,
            'baseCurrency' => 'EUR',
            'openingBalance' => $opening,
            'openingSources' => $openingSources,
            'excludedCurrencyBalances' => $excludedCurrencyBalances,
            'lowestBalance' => $lowestDay['closing'],
            'lowestBalanceOn' => $lowestDay['date'],
            'closingBalance' => $weeks->last()['closing'],
            'days' => $days,
            'weeks' => $weeks,
        ];
        if ($exact) {
            return $result;
        }
        // Preserve the numeric contract of legacy consumers; all arithmetic above is decimal.
        $numeric = function ($value, $key = null) use (&$numeric) {
            if ($value instanceof Collection) {
                return $value->map(fn ($v, $k) => $numeric($v, $k));
            }
            if (is_array($value)) {
                return collect($value)->map(fn ($v, $k) => $numeric($v, $k))->all();
            }

            return is_string($value) && in_array($key, ['openingBalance', 'lowestBalance', 'closingBalance', 'balance', 'opening', 'in', 'out', 'closing', 'amount', 'originalAmount'], true) ? (float) $value : $value;
        };

        return $numeric($result);
    }

    private function addEvent(Collection $events, CarbonImmutable $date, string $direction, mixed $originalAmount, mixed $scenarioAmount, string $sourceType, int $sourceId, string $label, string $href): void
    {
        $amount = $this->money($scenarioAmount);
        if (BigDecimal::of($amount)->isLessThanOrEqualTo('0')) {
            return;
        }
        $events->push([
            'date' => $date,
            'direction' => $direction,
            'amount' => $amount,
            'originalAmount' => $this->money($originalAmount),
            'sourceType' => $sourceType,
            'sourceId' => $sourceId,
            'label' => $label,
            'href' => $href,
        ]);
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

    private function money(mixed $value): string
    {
        return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp);
    }

    private function sum(Collection $items, string $key): string
    {
        return $this->money($items->reduce(fn ($total, $item) => $total->plus((string) $item[$key]), BigDecimal::zero()));
    }
}
