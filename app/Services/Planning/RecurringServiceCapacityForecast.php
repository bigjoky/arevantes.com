<?php

namespace App\Services\Planning;

use App\Models\MaintenancePlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class RecurringServiceCapacityForecast
{
    public function __construct(private WorkforceCapacityForecast $capacity) {}

    /** @return array<string, mixed> */
    public function forecast(CarbonInterface $from, int $weeks = 6, ?User $viewer = null): array
    {
        $start = CarbonImmutable::parse($from)->startOfWeek();
        $base = $this->capacity->forecast($start, $weeks, $viewer);
        $end = CarbonImmutable::parse($base['period']['to'])->endOfDay();
        $plans = MaintenancePlan::query()
            ->with([
                'installation:id,project_reference,site_name,service_type',
                'template:id,estimated_duration_minutes',
                'tickets' => fn ($query) => $query->whereNotIn('status', ['resolved', 'closed'])->select(['id', 'maintenance_plan_id']),
                'cycleCommitments' => fn ($query) => $query->where('status', 'approved')->select(['id', 'maintenance_plan_id', 'maintenance_plan_version', 'period_starts_on']),
            ])
            ->where('is_active', true)
            ->whereBetween('next_due_on', [$start, $end])
            ->when($viewer?->hasRole('technician'), fn ($query) => $query->where('assigned_user_id', $viewer->id))
            ->get()
            ->filter(fn (MaintenancePlan $plan) => $plan->tickets->isEmpty() && ! $plan->cycleCommitments->contains(fn ($commitment) => $commitment->maintenance_plan_version === $plan->version && $commitment->period_starts_on->isSameDay($plan->next_due_on)));

        $weeksRows = collect($base['weeks'])->map(function (array $week) use ($plans): array {
            $weekStart = CarbonImmutable::parse($week['week_start']);
            $weekEnd = CarbonImmutable::parse($week['week_end'])->endOfDay();
            $due = $plans->filter(fn (MaintenancePlan $plan) => $plan->next_due_on->betweenIncluded($weekStart, $weekEnd));
            $uncommitted = (int) $due->sum(fn (MaintenancePlan $plan) => (int) ($plan->template?->estimated_duration_minutes ?? 90));
            $remaining = (int) $week['remaining_minutes'];

            return [
                'week_start' => $week['week_start'],
                'week_end' => $week['week_end'],
                'remaining_minutes' => $remaining,
                'uncommitted_service_minutes' => $uncommitted,
                'projected_remaining_minutes' => max(0, $remaining - $uncommitted),
                'projected_overload_minutes' => max(0, $uncommitted - $remaining),
                'due_plans' => $due->count(),
                'unassigned_plans' => $due->whereNull('assigned_user_id')->count(),
                'critical_unassigned' => $due->where('service_level', 'critical')->whereNull('assigned_user_id')->count(),
                'plans' => $due->map(fn (MaintenancePlan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'version' => $plan->version,
                    'service_level' => $plan->service_level,
                    'due_on' => $plan->next_due_on->toDateString(),
                    'assigned_user_id' => $plan->assigned_user_id,
                    'estimated_duration_minutes' => (int) ($plan->template?->estimated_duration_minutes ?? 90),
                    'installation' => $plan->installation?->project_reference ?: $plan->installation?->site_name,
                ])->values()->all(),
            ];
        });

        return [
            'summary' => [
                'due_plans' => $weeksRows->sum('due_plans'),
                'unassigned_plans' => $weeksRows->sum('unassigned_plans'),
                'critical_unassigned' => $weeksRows->sum('critical_unassigned'),
                'uncommitted_service_minutes' => $weeksRows->sum('uncommitted_service_minutes'),
                'projected_overloaded_weeks' => $weeksRows->where('projected_overload_minutes', '>', 0)->count(),
            ],
            'weeks' => $weeksRows->values()->all(),
            'disclaimer' => __('Capacity preview only. Plans remain uncommitted until their work order is generated and scheduled.'),
        ];
    }
}
