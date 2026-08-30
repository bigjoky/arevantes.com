<?php

namespace App\Services\Planning;

use App\Models\CrmActivity;
use App\Models\FinancialForecastItem;
use App\Models\PlanningScenarioRequest;
use App\Models\PurchaseDocument;
use App\Models\StockReservation;
use Illuminate\Support\Collection;

class PlanningScenarioOutcomeMonitor
{
    /** @param Collection<int, PlanningScenarioRequest> $requests @return array<int, array<string, mixed>> */
    public function forMany(Collection $requests): array
    {
        $ids = $requests->pluck('id');
        $activities = CrmActivity::withTrashed()->whereIn('planning_scenario_request_id', $ids)->get();
        $reservations = StockReservation::whereIn('planning_scenario_request_id', $ids)->get();
        $purchases = PurchaseDocument::with('lines:id,purchase_document_id,quantity')->whereIn('planning_scenario_request_id', $ids)->get();
        $forecasts = FinancialForecastItem::whereIn('planning_scenario_request_id', $ids)->get();

        return $requests->mapWithKeys(function (PlanningScenarioRequest $request) use ($activities, $reservations, $purchases, $forecasts): array {
            $rows = match ($request->area) {
                'operations' => $activities->where('planning_scenario_request_id', $request->id),
                'inventory' => $reservations->where('planning_scenario_request_id', $request->id),
                'purchases' => $purchases->where('planning_scenario_request_id', $request->id),
                'finance' => $forecasts->where('planning_scenario_request_id', $request->id),
                default => collect(),
            };

            $monitoring = match ($request->area) {
                'operations' => [
                    'total' => $rows->count(), 'statuses' => $rows->countBy('status')->all(),
                    'unassigned' => $rows->whereIn('status', ['pending', 'in_progress', 'blocked'])->whereNull('assigned_user_id')->count(),
                    'overdue' => $rows->whereIn('status', ['pending', 'in_progress', 'blocked'])->filter(fn (CrmActivity $activity): bool => $activity->due_at?->isPast() ?? false)->count(),
                    'comparison' => [
                        ['label' => 'Tasks created', 'planned' => count($request->payload['opportunities'] ?? []), 'current' => $rows->count(), 'unit' => 'items'],
                        ['label' => 'Tasks completed', 'planned' => count($request->payload['opportunities'] ?? []), 'current' => $rows->where('status', 'completed')->count(), 'unit' => 'items'],
                    ],
                ],
                'inventory' => [
                    'total' => $rows->count(), 'statuses' => $rows->countBy('status')->all(),
                    'quantity' => round((float) $rows->sum('quantity'), 3),
                    'consumed_quantity' => round((float) $rows->sum('consumed_quantity'), 3),
                    'expired' => $rows->where('status', 'active')->filter(fn (StockReservation $reservation): bool => $reservation->reserved_until?->isPast() ?? false)->count(),
                    'comparison' => [
                        ['label' => 'Units reserved', 'planned' => round((float) collect($request->payload['materials'] ?? [])->sum('expected_quantity'), 3), 'current' => round((float) $rows->sum('quantity'), 3), 'unit' => 'units'],
                        ['label' => 'Units consumed', 'planned' => round((float) collect($request->payload['materials'] ?? [])->sum('expected_quantity'), 3), 'current' => round((float) $rows->sum('consumed_quantity'), 3), 'unit' => 'units'],
                    ],
                ],
                'purchases' => [
                    'total' => $rows->count(), 'statuses' => $rows->countBy('status')->all(),
                    'amount' => round((float) $rows->sum('amount'), 2),
                    'comparison' => [
                        ['label' => 'Units requested', 'planned' => round((float) collect($request->payload['shortages'] ?? [])->sum('shortage_quantity'), 3), 'current' => round((float) $rows->sum(fn (PurchaseDocument $document): float => (float) $document->lines->sum('quantity')), 3), 'unit' => 'units'],
                        ['label' => 'Purchase subtotal', 'planned' => round((float) collect($request->execution_result['drafts'] ?? [])->sum(fn (array $row): float => (float) ($row['shortage_quantity'] ?? 0) * (float) (data_get($row, 'recommended_source.unit_price') ?? 0)), 2), 'current' => round((float) $rows->sum('subtotal'), 2), 'unit' => 'currency'],
                    ],
                ],
                'finance' => [
                    'total' => $rows->count(), 'statuses' => $rows->countBy('status')->all(),
                    'amount' => round((float) $rows->sum('amount'), 2),
                    'actual_amount' => round((float) $rows->sum('actual_amount'), 2),
                    'comparison' => [
                        ['label' => 'Expected collections', 'planned' => round((float) collect($request->payload['cash_by_month'] ?? [])->sum('expected_inflow'), 2), 'current' => round((float) $rows->where('direction', 'in')->sum('amount'), 2), 'unit' => 'currency'],
                        ['label' => 'Expected material payments', 'planned' => round((float) collect($request->payload['cash_by_month'] ?? [])->sum('expected_material_outflow'), 2), 'current' => round((float) $rows->where('direction', 'out')->sum('amount'), 2), 'unit' => 'currency'],
                    ],
                ],
                default => ['total' => 0, 'statuses' => []],
            };
            $exceptions = collect([
                ['label' => 'Unassigned tasks', 'count' => (int) ($monitoring['unassigned'] ?? 0)],
                ['label' => 'Overdue tasks', 'count' => (int) ($monitoring['overdue'] ?? 0)],
                ['label' => 'Expired reservations', 'count' => (int) ($monitoring['expired'] ?? 0)],
            ])->where('count', '>', 0)->values()->all();
            $year = substr((string) data_get($request->payload, 'horizon.from', now()->year), 0, 4);
            $url = match ($request->area) {
                'operations' => route('operations.index', ['planning_request' => $request->id, 'status' => 'all', 'view' => 'today'], false),
                'inventory' => route('inventory.index', ['planning_request' => $request->id], false),
                'purchases' => route('purchases.index', ['planning_request' => $request->id], false),
                'finance' => route('finance.index', ['planning_request' => $request->id, 'year' => $year], false).'#forecast-items',
                default => null,
            };

            return [$request->id => [...$monitoring, 'exceptions' => $exceptions, 'direct_url' => $url]];
        })->all();
    }
}
