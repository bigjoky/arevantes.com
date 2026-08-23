<?php

namespace App\Services\Supply;

use App\Models\SupplierProduct;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SupplyScenarioPlanner
{
    public function __construct(
        private readonly CriticalMaterialRisk $materialRisk,
        private readonly SupplierScorecards $scorecards,
    ) {}

    /** @return array<string, mixed> */
    public function forecast(CarbonInterface $from, int $days = 45, ?User $viewer = null): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $risk = $this->materialRisk->forecast($start, $days, $viewer);
        $shortages = collect($risk['rows'])->where('shortage_quantity', '>', 0);

        if ($shortages->isEmpty()) {
            return [...$risk, 'scenario' => $this->emptyScenario()];
        }

        $sources = SupplierProduct::query()
            ->with([
                'supplier:id,legal_name,trade_name,payment_terms_days,is_active',
                'product:id,cost_price,tax_rate_id,preferred_lead_time_days',
                'product.taxRate:id,rate',
            ])
            ->whereIn('product_id', $shortages->pluck('product_id')->unique())
            ->where('is_active', true)
            ->whereHas('supplier', fn ($query) => $query->where('is_active', true))
            ->get()
            ->groupBy('product_id');
        $scores = $this->scorecards->all()->keyBy('id');
        $scenarioRows = collect($risk['rows'])->map(function (array $row) use ($sources, $scores, $start): array {
            if ($row['shortage_quantity'] <= 0) {
                return [...$row, 'alternatives' => [], 'recommended' => null];
            }

            $alternatives = collect($sources->get($row['product_id'], collect()))
                ->map(function (SupplierProduct $source) use ($row, $scores, $start): array {
                    $minimum = max(0.001, (float) $source->minimum_order_quantity);
                    $multiple = max(0.001, (float) $source->order_multiple);
                    $purchaseQuantity = ceil(max((float) $row['shortage_quantity'], $minimum) / $multiple) * $multiple;
                    $leadTime = (int) ($source->lead_time_days ?? $source->product->preferred_lead_time_days ?? 0);
                    $arrival = $start->addDays($leadTime);
                    $payment = $arrival->addDays((int) $source->supplier->payment_terms_days);
                    $unitPrice = (float) ($source->last_unit_price ?? $source->product->cost_price);
                    $netCost = round($purchaseQuantity * $unitPrice, 2);
                    $currency = strtoupper($source->currency ?: 'EUR');
                    $taxRate = (float) ($source->product->taxRate?->rate ?? 0);
                    $cashImpact = $currency === 'EUR' ? round($netCost * (1 + $taxRate / 100), 2) : null;
                    $score = $scores->get($source->supplier_id);

                    return [
                        'supplier_id' => $source->supplier_id,
                        'supplier' => $source->supplier->trade_name ?: $source->supplier->legal_name,
                        'supplier_product_id' => $source->id,
                        'is_preferred' => $source->is_preferred,
                        'lead_time_days' => $leadTime,
                        'arrival_date' => $arrival->toDateString(),
                        'arrives_on_time' => $arrival->lte(CarbonImmutable::parse($row['scheduled_start'])),
                        'purchase_quantity' => round($purchaseQuantity, 3),
                        'unit_price' => round($unitPrice, 2),
                        'price_source' => $source->last_unit_price !== null ? 'supplier_history' : 'catalog_estimate',
                        'currency' => $currency,
                        'net_cost' => $netCost,
                        'cash_impact' => $cashImpact,
                        'payment_date' => $payment->toDateString(),
                        'payment_terms_days' => (int) $source->supplier->payment_terms_days,
                        'overall_score' => $score['overall_score'] ?? null,
                        'delivery_score' => $score['delivery_score'] ?? null,
                        'quality_score' => $score['quality_score'] ?? null,
                    ];
                })
                ->sortBy(fn (array $alternative) => [
                    $alternative['arrives_on_time'] ? 0 : 1,
                    $alternative['currency'] === 'EUR' ? 0 : 1,
                    $alternative['is_preferred'] ? 0 : 1,
                    -($alternative['overall_score'] ?? 0),
                    $alternative['net_cost'],
                ])
                ->values()
                ->take(5);
            $recommended = $alternatives->first();

            return [
                ...$row,
                'alternatives' => $alternatives->all(),
                'recommended' => $recommended,
            ];
        });
        $recommendations = $scenarioRows->pluck('recommended')->filter();

        return [
            ...$risk,
            'rows' => $scenarioRows->all(),
            'scenario' => [
                'items_with_alternatives' => $scenarioRows->whereNotNull('recommended')->count(),
                'items_without_alternatives' => $shortages->count() - $scenarioRows->whereNotNull('recommended')->count(),
                'covered_on_time' => $recommendations->where('arrives_on_time', true)->count(),
                'recommended_net' => round((float) $recommendations->where('currency', 'EUR')->sum('net_cost'), 2),
                'recommended_cash' => round((float) $recommendations->sum('cash_impact'), 2),
                'cash_within_30_days' => round((float) $recommendations
                    ->filter(fn (array $item) => $item['cash_impact'] !== null && CarbonImmutable::parse($item['payment_date'])->lte($start->addDays(30)))
                    ->sum('cash_impact'), 2),
            ],
        ];
    }

    /** @return array<string, int|float> */
    private function emptyScenario(): array
    {
        return [
            'items_with_alternatives' => 0,
            'items_without_alternatives' => 0,
            'covered_on_time' => 0,
            'recommended_net' => 0.0,
            'recommended_cash' => 0.0,
            'cash_within_30_days' => 0.0,
        ];
    }
}
