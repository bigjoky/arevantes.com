<?php

namespace App\Services\Planning;

use App\Models\Opportunity;
use App\Models\PlanningScenario;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SharedPortfolioScenario
{
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
        $fingerprint = hash('sha256', json_encode([$assumptions, $snapshot, $results], JSON_THROW_ON_ERROR));

        return DB::transaction(fn () => PlanningScenario::create([
            'name' => $data['name'],
            'version' => ((int) PlanningScenario::where('name', $data['name'])->max('version')) + 1,
            'status' => 'frozen',
            'horizon_starts_on' => $data['horizon_starts_on'],
            'horizon_ends_on' => $data['horizon_ends_on'],
            'assumptions' => $assumptions,
            'opportunity_snapshot' => $snapshot,
            'result_snapshot' => $results,
            'fingerprint' => $fingerprint,
            'created_by' => $actor->id,
        ]));
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
