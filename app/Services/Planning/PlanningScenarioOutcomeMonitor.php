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
        $purchases = PurchaseDocument::whereIn('planning_scenario_request_id', $ids)->get();
        $forecasts = FinancialForecastItem::whereIn('planning_scenario_request_id', $ids)->get();

        return $requests->mapWithKeys(function (PlanningScenarioRequest $request) use ($activities, $reservations, $purchases, $forecasts): array {
            $rows = match ($request->area) {
                'operations' => $activities->where('planning_scenario_request_id', $request->id),
                'inventory' => $reservations->where('planning_scenario_request_id', $request->id),
                'purchases' => $purchases->where('planning_scenario_request_id', $request->id),
                'finance' => $forecasts->where('planning_scenario_request_id', $request->id),
                default => collect(),
            };

            return [$request->id => match ($request->area) {
                'operations' => [
                    'total' => $rows->count(), 'statuses' => $rows->countBy('status')->all(),
                    'unassigned' => $rows->whereNull('assigned_user_id')->count(),
                    'overdue' => $rows->whereIn('status', ['pending', 'in_progress'])->filter(fn (CrmActivity $activity): bool => $activity->due_at?->isPast() ?? false)->count(),
                ],
                'inventory' => [
                    'total' => $rows->count(), 'statuses' => $rows->countBy('status')->all(),
                    'quantity' => round((float) $rows->sum('quantity'), 3),
                    'consumed_quantity' => round((float) $rows->sum('consumed_quantity'), 3),
                    'expired' => $rows->where('status', 'active')->filter(fn (StockReservation $reservation): bool => $reservation->reserved_until?->isPast() ?? false)->count(),
                ],
                'purchases' => [
                    'total' => $rows->count(), 'statuses' => $rows->countBy('status')->all(),
                    'amount' => round((float) $rows->sum('amount'), 2),
                ],
                'finance' => [
                    'total' => $rows->count(), 'statuses' => $rows->countBy('status')->all(),
                    'amount' => round((float) $rows->sum('amount'), 2),
                    'actual_amount' => round((float) $rows->sum('actual_amount'), 2),
                ],
                default => ['total' => 0, 'statuses' => []],
            }];
        })->all();
    }
}
