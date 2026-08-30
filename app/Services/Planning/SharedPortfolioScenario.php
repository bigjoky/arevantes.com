<?php

namespace App\Services\Planning;

use App\Models\Opportunity;
use App\Models\PlanningScenario;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SharedPortfolioScenario
{
    public function __construct(
        private WorkforceCapacityForecast $capacity,
        private PlanningScenarioIntegrity $integrity,
    ) {}

    /** @param array<string, mixed> $data */
    public function freeze(array $data, User $actor): PlanningScenario
    {
        $ids = collect($data['opportunity_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $opportunities = Opportunity::query()
            ->whereIn('id', $ids)
            ->whereNotIn('stage', ['won', 'lost'])
            ->with([
                'customer:id,first_name,last_name,company_name',
                'installation:id,service_type,project_reference,site_name',
                'quote.lines.product:id,sku,name,cost_price,track_stock',
                'quote.lines.components.product:id,sku,name,cost_price,track_stock',
            ])
            ->get()
            ->keyBy('id');

        if ($opportunities->count() !== $ids->count()) {
            throw ValidationException::withMessages(['opportunity_ids' => __('Every selected opportunity must exist and remain open.')]);
        }

        $assumptions = [
            'capacity_hours_per_opportunity' => round((float) $data['capacity_hours_per_opportunity'], 2),
            'material_cost_percent_without_quote' => round((float) $data['material_cost_percent_without_quote'], 2),
            'collection_delay_days' => (int) $data['collection_delay_days'],
        ];
        $horizonEnd = Carbon::parse($data['horizon_ends_on']);
        $rows = $ids->map(fn (int $id) => $this->snapshotOpportunity($opportunities[$id], $assumptions, $horizonEnd));
        $results = $this->results($rows);
        $snapshot = ['opportunities' => $rows->all()];
        $baseline = $this->baseline($data);
        $references = $this->references($results, $data, $actor);
        $comparison = $baseline ? $this->compare($baseline, $results, $references, $snapshot) : null;
        $fingerprint = $this->integrity->fromSnapshots($assumptions, $snapshot, $results, $references, $comparison);

        return DB::transaction(fn () => PlanningScenario::create([
            'name' => $data['name'],
            'version' => ((int) PlanningScenario::where('name', $data['name'])->max('version')) + 1,
            'status' => 'frozen',
            'scenario_kind' => $data['scenario_kind'],
            'baseline_scenario_id' => $baseline?->id,
            'horizon_starts_on' => $data['horizon_starts_on'],
            'horizon_ends_on' => $data['horizon_ends_on'],
            'assumptions' => $assumptions,
            'opportunity_snapshot' => $snapshot,
            'result_snapshot' => $results,
            'reference_snapshot' => $references,
            'reference_valid_until' => now()->addDay(),
            'comparison_snapshot' => $comparison,
            'fingerprint' => $fingerprint,
            'created_by' => $actor->id,
        ]));
    }

    /** @param array<string, mixed> $data */
    private function baseline(array $data): ?PlanningScenario
    {
        if ($data['scenario_kind'] === 'base') {
            return null;
        }

        $baseline = PlanningScenario::query()->whereKey($data['baseline_scenario_id'])->where('scenario_kind', 'base')->where('status', 'frozen')->first();
        if (! $baseline) {
            throw ValidationException::withMessages(['baseline_scenario_id' => __('Risk and alternative scenarios require a frozen base scenario.')]);
        }

        return $baseline;
    }

    /** @param array<string, mixed> $results @param array<string, mixed> $data */
    private function references(array $results, array $data, User $actor): array
    {
        $start = Carbon::parse($data['horizon_starts_on'])->startOfWeek();
        $end = Carbon::parse($data['horizon_ends_on']);
        $weeks = max(1, min(12, (int) ceil(($start->diffInDays($end) + 1) / 7)));
        $capacity = $this->capacity->forecast($start, $weeks, $actor);
        $productIds = collect($results['materials'])->pluck('product_id');
        $onHand = StockMovementLine::query()->whereIn('product_id', $productIds)->groupBy('product_id')->selectRaw('product_id, sum(quantity) quantity')->pluck('quantity', 'product_id');
        $reserved = StockReservation::query()->whereIn('product_id', $productIds)->where('status', 'active')->groupBy('product_id')
            ->selectRaw('product_id, sum(quantity - consumed_quantity) quantity')->pluck('quantity', 'product_id');
        $stock = collect($results['materials'])->map(function (array $material) use ($onHand, $reserved): array {
            $onHandQuantity = (float) ($onHand[$material['product_id']] ?? 0);
            $reservedQuantity = (float) ($reserved[$material['product_id']] ?? 0);
            $free = max(0, $onHandQuantity - $reservedQuantity);

            return [
                ...$material,
                'on_hand_quantity' => round($onHandQuantity, 3),
                'reserved_quantity' => round($reservedQuantity, 3),
                'free_quantity' => round($free, 3),
                'shortage_quantity' => round(max(0, (float) $material['expected_quantity'] - $free), 3),
            ];
        })->values();

        return [
            'captured_at' => now()->toIso8601String(),
            'capacity' => [
                'period' => $capacity['period'],
                'available_hours' => round($capacity['summary']['available_minutes'] / 60, 2),
                'planned_hours' => round($capacity['summary']['planned_minutes'] / 60, 2),
                'remaining_hours' => round($capacity['summary']['remaining_minutes'] / 60, 2),
                'scenario_demand_hours' => $results['summary']['capacity_hours'],
                'scenario_overload_hours' => round(max(0, $results['summary']['capacity_hours'] - ($capacity['summary']['remaining_minutes'] / 60)), 2),
            ],
            'stock' => [
                'products' => $stock->all(),
                'products_at_risk' => $stock->where('shortage_quantity', '>', 0)->count(),
                'expected_units' => round((float) $stock->sum('expected_quantity'), 3),
                'shortage_units' => round((float) $stock->sum('shortage_quantity'), 3),
            ],
        ];
    }

    /** @param array<string, mixed> $results @param array<string, mixed> $references @param array<string, mixed> $snapshot */
    private function compare(PlanningScenario $baseline, array $results, array $references, array $snapshot): array
    {
        $baselineSummary = $baseline->result_snapshot['summary'];
        $baselineReferences = $baseline->reference_snapshot ?? [];
        $baselineIds = collect($baseline->opportunity_snapshot['opportunities'])->pluck('opportunity_id');
        $candidateIds = collect($snapshot['opportunities'])->pluck('opportunity_id');
        $metrics = ['confirmed_value', 'weighted_revenue', 'capacity_hours', 'expected_material_cost'];

        return [
            'baseline_id' => $baseline->id,
            'baseline_name' => $baseline->name,
            'baseline_version' => $baseline->version,
            'deltas' => collect($metrics)->mapWithKeys(fn (string $metric): array => [
                $metric => round((float) $results['summary'][$metric] - (float) $baselineSummary[$metric], 2),
            ])->all(),
            'capacity' => [
                'remaining_hours_delta' => round((float) $references['capacity']['remaining_hours'] - (float) data_get($baselineReferences, 'capacity.remaining_hours', 0), 2),
                'overload_hours_delta' => round((float) $references['capacity']['scenario_overload_hours'] - (float) data_get($baselineReferences, 'capacity.scenario_overload_hours', 0), 2),
            ],
            'stock' => [
                'products_at_risk_delta' => (int) $references['stock']['products_at_risk'] - (int) data_get($baselineReferences, 'stock.products_at_risk', 0),
                'shortage_units_delta' => round((float) $references['stock']['shortage_units'] - (float) data_get($baselineReferences, 'stock.shortage_units', 0), 3),
            ],
            'portfolio' => [
                'added_opportunity_ids' => $candidateIds->diff($baselineIds)->values()->all(),
                'removed_opportunity_ids' => $baselineIds->diff($candidateIds)->values()->all(),
            ],
        ];
    }

    /** @param array<string, int|float> $assumptions */
    private function snapshotOpportunity(Opportunity $opportunity, array $assumptions, Carbon $horizonEnd): array
    {
        $quote = $opportunity->quote;
        $value = (float) ($quote?->amount ?? $opportunity->estimated_value);
        $probability = (int) $opportunity->probability;
        $weight = $probability / 100;
        $components = collect();

        if ($quote) {
            $components = $quote->lines->where('is_selected', true)->flatMap(function ($line) {
                $lineComponents = $line->components->filter(fn ($component) => $component->product && in_array($component->fulfillment_kind, ['stock', 'material'], true))
                    ->map(fn ($component): array => [
                        'product_id' => $component->product_id,
                        'sku' => $component->product->sku,
                        'name' => $component->product->name,
                        'quantity' => $line->effectiveComponentQuantity($component),
                        'unit_cost' => (float) ($component->planned_unit_cost ?? $component->product->cost_price),
                    ]);

                return $lineComponents->isNotEmpty() ? $lineComponents : ($line->product
                    ? collect([[
                        'product_id' => $line->product_id,
                        'sku' => $line->product->sku,
                        'name' => $line->product->name,
                        'quantity' => (float) $line->quantity,
                        'unit_cost' => (float) $line->product->cost_price,
                    ]])
                    : collect());
            });
        }

        $materials = $components->groupBy('product_id')->map(fn (Collection $items): array => [
            ...$items->first(),
            'quantity' => round((float) $items->sum('quantity'), 3),
        ])->values();
        $confirmedMaterialCost = (float) $materials->sum(fn (array $material) => $material['quantity'] * $material['unit_cost']);
        $materialCost = $materials->isNotEmpty()
            ? $confirmedMaterialCost
            : $value * ((float) $assumptions['material_cost_percent_without_quote'] / 100);
        $closeOn = $opportunity->expected_close_on ?: $horizonEnd;

        return [
            'opportunity_id' => $opportunity->id,
            'confirmed' => [
                'code' => $opportunity->code,
                'title' => $opportunity->title,
                'customer' => $opportunity->customer?->company_name ?: trim(($opportunity->customer?->first_name ?? '').' '.($opportunity->customer?->last_name ?? '')),
                'business_line' => $opportunity->installation?->service_type,
                'stage' => $opportunity->stage,
                'probability' => $probability,
                'value' => round($value, 2),
                'value_source' => $quote ? 'quote' : 'opportunity',
                'expected_close_on' => $opportunity->expected_close_on?->toDateString(),
                'quote_id' => $quote?->id,
                'materials' => $materials->all(),
            ],
            'projected' => [
                'weighted_revenue' => round($value * $weight, 2),
                'capacity_hours' => round((float) $assumptions['capacity_hours_per_opportunity'] * $weight, 2),
                'material_cost' => round($materialCost * $weight, 2),
                'material_cost_source' => $materials->isNotEmpty() ? 'quote_composition' : 'assumption',
                'cash_in_on' => $closeOn->copy()->addDays((int) $assumptions['collection_delay_days'])->toDateString(),
            ],
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function results(Collection $rows): array
    {
        $materials = $rows->flatMap(fn (array $row) => collect($row['confirmed']['materials'])->map(fn (array $material): array => [
            ...$material,
            'expected_quantity' => round($material['quantity'] * ($row['confirmed']['probability'] / 100), 3),
        ]))->groupBy('product_id')->map(fn (Collection $items): array => [
            'product_id' => $items->first()['product_id'],
            'sku' => $items->first()['sku'],
            'name' => $items->first()['name'],
            'expected_quantity' => round((float) $items->sum('expected_quantity'), 3),
        ])->values();
        $cash = $rows->groupBy(fn (array $row) => substr($row['projected']['cash_in_on'], 0, 7))->map(fn (Collection $items, string $month): array => [
            'month' => $month,
            'expected_inflow' => round((float) $items->sum('projected.weighted_revenue'), 2),
            'expected_material_outflow' => round((float) $items->sum('projected.material_cost'), 2),
        ])->sortBy('month')->values();

        return [
            'summary' => [
                'opportunities' => $rows->count(),
                'confirmed_value' => round((float) $rows->sum('confirmed.value'), 2),
                'weighted_revenue' => round((float) $rows->sum('projected.weighted_revenue'), 2),
                'capacity_hours' => round((float) $rows->sum('projected.capacity_hours'), 2),
                'expected_material_cost' => round((float) $rows->sum('projected.material_cost'), 2),
                'quoted_opportunities' => $rows->whereNotNull('confirmed.quote_id')->count(),
            ],
            'materials' => $materials->all(),
            'cash_by_month' => $cash->all(),
            'non_committing' => true,
        ];
    }
}
