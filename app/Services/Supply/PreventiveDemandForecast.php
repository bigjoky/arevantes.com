<?php

namespace App\Services\Supply;

use App\Models\MaintenancePlan;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PreventiveDemandForecast
{
    /** @return array<string, mixed> */
    public function forecast(CarbonInterface $from, int $days = 180): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = $start->addDays(max(1, min($days, 365)))->endOfDay();
        $plans = MaintenancePlan::query()
            ->with([
                'installation:id,project_reference,site_name,service_type',
                'preventiveMaterials.product:id,sku,name,cost_price,supply_criticality,preferred_lead_time_days',
                'tickets' => fn ($query) => $query->whereNotIn('status', ['resolved', 'closed'])->select(['id', 'maintenance_plan_id']),
            ])
            ->where('is_active', true)
            ->whereNotNull('next_due_on')
            ->whereDate('next_due_on', '<=', $end)
            ->whereHas('preventiveMaterials')
            ->get();

        $productIds = $plans->flatMap->preventiveMaterials->pluck('product_id')->unique()->values();
        if ($productIds->isEmpty()) {
            return $this->empty($start, $end);
        }

        $onHand = StockMovementLine::query()
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->selectRaw('product_id, sum(quantity) quantity')
            ->pluck('quantity', 'product_id');
        $reserved = StockReservation::query()
            ->whereIn('product_id', $productIds)
            ->where('status', 'active')
            ->groupBy('product_id')
            ->selectRaw('product_id, sum(quantity - consumed_quantity) quantity')
            ->pluck('quantity', 'product_id');
        $inbound = DB::table('purchase_document_lines')
            ->join('purchase_documents', 'purchase_documents.id', '=', 'purchase_document_lines.purchase_document_id')
            ->where('purchase_documents.type', 'purchase_order')
            ->whereIn('purchase_documents.status', ['ordered', 'partially_received'])
            ->whereIn('purchase_document_lines.product_id', $productIds)
            ->where(function ($query) use ($end): void {
                $query->whereDate('purchase_documents.supplier_confirmed_delivery_date', '<=', $end)
                    ->orWhere(fn ($fallback) => $fallback->whereNull('purchase_documents.supplier_confirmed_delivery_date')->whereDate('purchase_documents.expected_delivery_date', '<=', $end));
            })
            ->groupBy('purchase_document_lines.product_id')
            ->selectRaw('purchase_document_lines.product_id, sum(purchase_document_lines.quantity - purchase_document_lines.received_quantity) quantity')
            ->pluck('quantity', 'product_id');

        $demands = collect();
        foreach ($plans as $plan) {
            $visits = $this->visits($plan, $start, $end);
            if ($visits === 0) {
                continue;
            }
            foreach ($plan->preventiveMaterials as $material) {
                if (! $material->product) {
                    continue;
                }
                $expected = (float) $material->quantity_per_visit * $visits * ((float) $material->expected_usage_percent / 100);
                $demands->push([
                    'product_id' => $material->product_id,
                    'product' => $material->product,
                    'plan_id' => $plan->id,
                    'plan' => $plan->name,
                    'plan_version' => $plan->version,
                    'installation_id' => $plan->installation_id,
                    'installation' => $plan->installation?->project_reference ?: $plan->installation?->site_name,
                    'business_line' => $plan->installation?->service_type,
                    'visits' => $visits,
                    'first_due_on' => $plan->next_due_on->toDateString(),
                    'expected_quantity' => $expected,
                    'minimum_stock' => (float) $material->minimum_stock,
                ]);
            }
        }

        $rows = $demands->groupBy('product_id')->map(function ($items, $productId) use ($onHand, $reserved, $inbound): array {
            $product = $items->first()['product'];
            $expected = (float) $items->sum('expected_quantity');
            $minimum = (float) $items->max('minimum_stock');
            $stock = (float) ($onHand[$productId] ?? 0);
            $committed = (float) ($reserved[$productId] ?? 0);
            $incoming = max(0, (float) ($inbound[$productId] ?? 0));
            $free = max(0, $stock - $committed);
            $shortage = max(0, $expected + $minimum - $free - $incoming);

            return [
                'product_id' => (int) $productId,
                'sku' => $product->sku,
                'product' => $product->name,
                'criticality' => $product->supply_criticality,
                'preferred_lead_time_days' => $product->preferred_lead_time_days,
                'expected_quantity' => round($expected, 3),
                'minimum_stock' => round($minimum, 3),
                'on_hand' => round($stock, 3),
                'project_reserved' => round($committed, 3),
                'free_stock' => round($free, 3),
                'inbound_before_horizon' => round($incoming, 3),
                'forecast_shortage' => round($shortage, 3),
                'forecast_exposure' => round($shortage * (float) $product->cost_price, 2),
                'plans' => $items->map(fn (array $item) => collect($item)->except('product')->all())->values()->all(),
            ];
        })->sortBy(fn (array $row) => [$row['forecast_shortage'] > 0 ? 0 : 1, $row['criticality'] === 'critical' ? 0 : 1, -$row['forecast_exposure']])->values();

        return [
            'period' => ['from' => $start->toDateString(), 'to' => $end->toDateString(), 'days' => $start->diffInDays($end)],
            'summary' => [
                'products' => $rows->count(),
                'plans' => $demands->pluck('plan_id')->unique()->count(),
                'visits' => $demands->groupBy('plan_id')->sum(fn ($items) => $items->first()['visits']),
                'products_at_risk' => $rows->where('forecast_shortage', '>', 0)->count(),
                'expected_units' => round((float) $rows->sum('expected_quantity'), 3),
                'forecast_exposure' => round((float) $rows->sum('forecast_exposure'), 2),
            ],
            'rows' => $rows->all(),
            'disclaimer' => __('Forecast only. It does not reserve stock or create purchasing documents.'),
        ];
    }

    private function visits(MaintenancePlan $plan, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $date = CarbonImmutable::parse($plan->next_due_on)->startOfDay();
        $visits = 0;
        while ($date->lte($end)) {
            if ($date->gte($start)) {
                $visits++;
            }
            $date = $date->addMonthsNoOverflow(max(1, (int) $plan->interval_months));
        }

        return max(0, $visits - ($plan->tickets->isNotEmpty() ? 1 : 0));
    }

    /** @return array<string, mixed> */
    private function empty(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return [
            'period' => ['from' => $start->toDateString(), 'to' => $end->toDateString(), 'days' => $start->diffInDays($end)],
            'summary' => ['products' => 0, 'plans' => 0, 'visits' => 0, 'products_at_risk' => 0, 'expected_units' => 0.0, 'forecast_exposure' => 0.0],
            'rows' => [],
            'disclaimer' => __('Forecast only. It does not reserve stock or create purchasing documents.'),
        ];
    }
}
