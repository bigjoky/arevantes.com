<?php

namespace App\Services\Planning;

use App\Models\HrLeaveRequest;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class WorkforceCapacityForecast
{
    /**
     * Build a privacy-safe weekly capacity forecast. Leave reasons and HR notes
     * never leave this service; operations only receives unavailable minutes.
     *
     * @return array<string, mixed>
     */
    public function forecast(CarbonInterface $from, int $weeks = 6, ?User $viewer = null): array
    {
        $weeks = max(1, min($weeks, 12));
        $start = CarbonImmutable::parse($from)->startOfWeek();
        $end = $start->addWeeks($weeks)->subSecond();

        $technicians = User::query()
            ->where('role', 'technician')
            ->when($viewer && ! $viewer->can('manage-installations'), fn ($query) => $query->whereKey($viewer->id))
            ->with([
                'fieldServiceProfile:user_id,daily_capacity_minutes,is_available',
                'staffProfile:id',
                'staffProfile.employmentProfile:id,person_id,status,weekly_hours,start_date,end_date',
            ])
            ->orderBy('name')
            ->get(['id', 'person_id', 'name']);

        $technicianIds = $technicians->pluck('id');
        $personIds = $technicians->pluck('person_id')->filter();
        $orders = WorkOrder::query()
            ->whereIn('assigned_user_id', $technicianIds)
            ->whereIn('status', ['planned', 'in_progress', 'blocked'])
            ->whereNotNull('scheduled_start')
            ->whereBetween('scheduled_start', [$start, $end])
            ->get(['id', 'assigned_user_id', 'scheduled_start', 'scheduled_end', 'estimated_duration_minutes']);
        $leaves = HrLeaveRequest::query()
            ->whereIn('person_id', $personIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get(['id', 'person_id', 'start_date', 'end_date']);

        $forecastWeeks = collect(range(0, $weeks - 1))->map(function (int $offset) use ($start, $technicians, $orders, $leaves): array {
            $weekStart = $start->addWeeks($offset);
            $weekEnd = $weekStart->endOfWeek();
            $rows = $technicians->map(fn (User $technician) => $this->technicianWeek($technician, $weekStart, $weekEnd, $orders, $leaves));

            return [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                ...$this->totals($rows),
                'technicians' => $rows->values()->all(),
            ];
        });

        return [
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'weeks' => $weeks,
            ],
            'summary' => [
                ...$this->totals($forecastWeeks),
                'overloaded_weeks' => $forecastWeeks->where('overload_minutes', '>', 0)->count(),
            ],
            'weeks' => $forecastWeeks->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, WorkOrder>  $orders
     * @param  Collection<int, HrLeaveRequest>  $leaves
     * @return array<string, int|float|string>
     */
    private function technicianWeek(User $technician, CarbonImmutable $weekStart, CarbonImmutable $weekEnd, Collection $orders, Collection $leaves): array
    {
        $profile = $technician->fieldServiceProfile;
        $employment = $technician->staffProfile?->employmentProfile;
        $dailyMinutes = $profile?->daily_capacity_minutes
            ?? ($employment?->weekly_hours ? (int) round(((float) $employment->weekly_hours * 60) / 5) : 480);
        $isEmployed = $employment === null || (
            $employment->status === 'active'
            && ($employment->start_date === null || $employment->start_date->lte($weekEnd))
            && ($employment->end_date === null || $employment->end_date->gte($weekStart))
        );
        $capacity = ($profile?->is_available ?? true) && $isEmployed ? $dailyMinutes * 5 : 0;
        $unavailable = min($capacity, $leaves
            ->where('person_id', $technician->person_id)
            ->sum(fn (HrLeaveRequest $leave) => $this->businessDays(
                CarbonImmutable::parse($leave->start_date)->max($weekStart),
                CarbonImmutable::parse($leave->end_date)->min($weekEnd),
            ) * $dailyMinutes));
        $available = max(0, $capacity - $unavailable);
        $planned = (int) $orders
            ->where('assigned_user_id', $technician->id)
            ->filter(fn (WorkOrder $order) => $order->scheduled_start?->betweenIncluded($weekStart, $weekEnd))
            ->sum(fn (WorkOrder $order) => $this->orderMinutes($order));

        return [
            'user_id' => $technician->id,
            'name' => $technician->name,
            ...$this->metrics($capacity, $unavailable, $available, $planned),
        ];
    }

    private function businessDays(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        $days = 0;
        for ($date = $start->startOfDay(); $date->lte($end); $date = $date->addDay()) {
            $days += $date->isWeekday() ? 1 : 0;
        }

        return $days;
    }

    private function orderMinutes(WorkOrder $order): int
    {
        if ($order->scheduled_start && $order->scheduled_end && $order->scheduled_end->gt($order->scheduled_start)) {
            return (int) round($order->scheduled_start->diffInMinutes($order->scheduled_end));
        }

        return max(0, (int) ($order->estimated_duration_minutes ?? 60));
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function totals(Collection $rows): array
    {
        return $this->metrics(
            (int) $rows->sum('capacity_minutes'),
            (int) $rows->sum('unavailable_minutes'),
            (int) $rows->sum('available_minutes'),
            (int) $rows->sum('planned_minutes'),
        );
    }

    /** @return array<string, int|float> */
    private function metrics(int $capacity, int $unavailable, int $available, int $planned): array
    {
        return [
            'capacity_minutes' => $capacity,
            'unavailable_minutes' => $unavailable,
            'available_minutes' => $available,
            'planned_minutes' => $planned,
            'remaining_minutes' => max(0, $available - $planned),
            'overload_minutes' => max(0, $planned - $available),
            'utilization_percent' => $available > 0 ? round(($planned / $available) * 100, 1) : ($planned > 0 ? 100.0 : 0.0),
        ];
    }
}
