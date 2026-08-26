<?php

namespace App\Services\AfterSales;

use App\Models\MaintenancePlan;
use App\Models\ServiceCycleCommitment;
use App\Models\User;
use App\Services\Planning\RecurringServiceCapacityForecast;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceCycleCommitmentWorkflow
{
    public function __construct(private RecurringServiceCapacityForecast $capacity) {}

    public function approve(MaintenancePlan $plan, int $expectedVersion, float $plannedTravelKm, string $note, User $actor): ServiceCycleCommitment
    {
        $existing = ServiceCycleCommitment::query()
            ->where('maintenance_plan_id', $plan->id)
            ->where('maintenance_plan_version', $expectedVersion)
            ->whereDate('period_starts_on', $plan->next_due_on)
            ->first();
        if ($existing) {
            return $existing;
        }

        $forecast = $this->capacity->forecast(today(), 6, $actor);
        $week = collect($forecast['weeks'])->first(fn (array $row) => collect($row['plans'] ?? [])->contains('id', $plan->id));
        if (! $week) {
            throw ValidationException::withMessages(['commitment' => __('The next visit must fall within the six-week capacity horizon before it can be approved.')]);
        }
        $planRow = collect($week['plans'])->firstWhere('id', $plan->id);
        if (! $planRow['assigned_user_id']) {
            throw ValidationException::withMessages(['commitment' => __('Assign a responsible technician before approving this service cycle.')]);
        }
        if ($week['projected_overload_minutes'] > 0) {
            throw ValidationException::withMessages(['commitment' => __('This week exceeds available capacity by :minutes minutes.', ['minutes' => $week['projected_overload_minutes']])]);
        }

        return DB::transaction(function () use ($plan, $expectedVersion, $plannedTravelKm, $note, $actor, $week, $planRow): ServiceCycleCommitment {
            $locked = MaintenancePlan::query()->with(['preventiveMaterials.product:id,cost_price'])->lockForUpdate()->findOrFail($plan->id);
            if (! $locked->is_active || $locked->version !== $expectedVersion) {
                throw ValidationException::withMessages(['commitment' => __('The plan changed or is inactive. Review its current version before approving capacity.')]);
            }
            $periodStart = $locked->next_due_on->copy();
            $periodEnd = $periodStart->copy()->addMonthsNoOverflow(max(1, $locked->interval_months))->subDay();
            $existing = ServiceCycleCommitment::query()
                ->where('maintenance_plan_id', $locked->id)
                ->where('maintenance_plan_version', $locked->version)
                ->whereDate('period_starts_on', $periodStart)
                ->first();
            if ($existing) {
                return $existing;
            }

            $minutes = (int) $planRow['estimated_duration_minutes'];
            $laborCost = ($minutes / 60) * (float) config('work.default_hourly_cost');
            $travelCost = $plannedTravelKm * (float) config('work.mileage_rate');
            $materialCost = $locked->preventiveMaterials->sum(fn ($material) => (float) $material->quantity_per_visit * ((float) $material->expected_usage_percent / 100) * (float) ($material->product?->cost_price ?? 0));
            $revenue = $locked->billing_amount && $locked->billing_frequency_months
                ? (float) $locked->billing_amount * (max(1, $locked->interval_months) / max(1, $locked->billing_frequency_months))
                : 0;

            return ServiceCycleCommitment::create([
                'maintenance_plan_id' => $locked->id,
                'maintenance_plan_version' => $locked->version,
                'period_starts_on' => $periodStart,
                'period_ends_on' => $periodEnd,
                'status' => 'approved',
                'planned_visits' => 1,
                'planned_minutes' => $minutes,
                'planned_travel_km' => $plannedTravelKm,
                'planned_revenue' => round($revenue, 2),
                'planned_labor_cost' => round($laborCost, 2),
                'planned_material_cost' => round($materialCost, 2),
                'planned_travel_cost' => round($travelCost, 2),
                'capacity_snapshot' => [
                    'week_start' => $week['week_start'],
                    'week_end' => $week['week_end'],
                    'remaining_minutes' => $week['remaining_minutes'],
                    'service_demand_minutes' => $week['uncommitted_service_minutes'],
                    'projected_remaining_minutes' => $week['projected_remaining_minutes'],
                    'assigned_user_id' => $planRow['assigned_user_id'],
                ],
                'approval_note' => trim($note),
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
        });
    }
}
