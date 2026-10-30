<?php

namespace App\Services\Work;

use App\Models\CrmActivity;
use App\Models\FieldServiceProfile;
use App\Models\HrCalendarHoliday;
use App\Models\HrEmploymentProfile;
use App\Models\HrLeaveRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\TechnicianSkills;
use App\Support\Time\BusinessTime;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TechnicianSchedule
{
    private array $dailyLoad = [];

    /** A read-only decision; unknown must never be advertised as confirmed availability. */
    public function availability(?int $userId, mixed $start, mixed $end, ?int $exceptOrderId = null, array $requiredSkills = [], ?string $serviceZone = null): array
    {
        $issues = [];
        $this->dailyLoad = [];
        try {
            $this->assertAvailable($userId, $start, $end, $exceptOrderId, $requiredSkills, $serviceZone);
        } catch (ValidationException $exception) {
            $issues = array_merge(...array_values($exception->errors()));
        }
        $profile = $userId ? FieldServiceProfile::where('user_id', $userId)->first() : null;
        $unknown = ! $userId || ! $start || ! $end || $profile?->weekly_schedule === null;
        $reason = __('Working hours are not configured. Availability cannot be confirmed.');
        if ($unknown && $issues === []) {
            $issues[] = $reason;
        }
        $status = $issues === [] ? 'available' : ((($unknown && $issues === [$reason]) || $issues === [__('An existing assignment has an incomplete schedule. Availability cannot be confirmed.')]) ? 'unknown' : 'unavailable');

        return ['status' => $status, 'issues' => $issues, 'timezone' => BusinessTime::zone(),
            'daily_capacity_minutes' => $profile?->daily_capacity_minutes, 'daily_load' => $this->dailyLoad,
            'required_skills' => array_map(TechnicianSkills::label(...), $requiredSkills)];
    }

    /** @param array<int, string> $requiredSkills */
    public function assertAvailable(?int $userId, mixed $start, mixed $end, ?int $exceptOrderId = null, array $requiredSkills = [], ?string $serviceZone = null, bool $allowPendingAbsenceWarning = false, ?int $exceptActivityId = null): void
    {
        if (! $userId) {
            return;
        }

        $profile = FieldServiceProfile::where('user_id', $userId)->first();
        if ($profile && ! $profile->is_available) {
            throw ValidationException::withMessages(['assigned_user_id' => __('This technician is not available for field service.')]);
        }
        $missingSkills = array_values(array_diff($requiredSkills, $profile?->skills ?? []));
        if ($missingSkills !== []) {
            throw ValidationException::withMessages(['assigned_user_id' => __('Technician lacks required skills: :skills.', ['skills' => implode(', ', array_map(TechnicianSkills::label(...), $missingSkills))])]);
        }
        if ($serviceZone && filled($profile?->service_zones) && ! in_array($serviceZone, $profile->service_zones, true)) {
            throw ValidationException::withMessages(['assigned_user_id' => __('Technician does not cover service zone :zone.', ['zone' => $serviceZone])]);
        }

        if (! $start && ! $end) {
            return;
        }
        if (! $start || ! $end) {
            throw ValidationException::withMessages(['scheduled_start' => __('The assignment must include both a start and an end.')]);
        }

        $start = BusinessTime::parse($start, 'scheduled_start');
        $end = BusinessTime::parse($end, 'scheduled_end');
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['scheduled_end' => __('The scheduled end must be after the scheduled start.')]);
        }

        if ($start->diffInDays($end) > 31) {
            throw ValidationException::withMessages(['scheduled_end' => __('An intervention cannot exceed 31 days.')]);
        }

        $personId = User::whereKey($userId)->value('person_id');
        if ($personId) {
            $employment = HrEmploymentProfile::where('person_id', $personId)->first();
            if ($employment && ($employment->status !== 'active' || ($employment->start_date && $employment->start_date->gt($start->copy()->startOfDay())) || ($employment->end_date && $employment->end_date->lt($start->copy()->startOfDay())))) {
                throw ValidationException::withMessages(['assigned_user_id' => 'El técnico no tiene una relación laboral activa para esta fecha.']);
            }
            if (HrLeaveRequest::where('person_id', $personId)->whereIn('status', $allowPendingAbsenceWarning ? ['approved'] : ['pending', 'approved'])->whereDate('start_date', '<=', $end->copy()->subSecond()->toDateString())->whereDate('end_date', '>=', $start->toDateString())->exists()) {
                throw ValidationException::withMessages(['assigned_user_id' => 'El técnico tiene una ausencia aprobada o pendiente de resolver para esta fecha.']);
            }
        }

        $conflict = WorkOrder::query()
            ->where('assigned_user_id', $userId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->when($exceptOrderId, fn ($query) => $query->where('id', '!=', $exceptOrderId))
            ->whereNotNull('scheduled_start')
            ->whereNotNull('scheduled_end')
            ->whereInstant('scheduled_start', '<', $end)
            ->whereInstant('scheduled_end', '>', $start)
            ->get(['id', 'number', 'scheduled_start', 'scheduled_end'])
            ->first(fn ($order) => $order->scheduled_start && $order->scheduled_end && $order->scheduled_start->lt($end) && $order->scheduled_end->gt($start));

        if ($conflict) {
            throw ValidationException::withMessages([
                'scheduled_start' => __('This technician is already assigned to :number from :start to :end.', [
                    'number' => $conflict->number,
                    'start' => $conflict->scheduled_start->format('d/m/Y H:i'),
                    'end' => $conflict->scheduled_end->format('d/m/Y H:i'),
                ]),
            ]);
        }

        $dayStart = $start->copy()->startOfDay();
        $dayEnd = $end->copy()->subMicrosecond()->endOfDay()->addMicrosecond();
        $orders = WorkOrder::where('assigned_user_id', $userId)->whereNotIn('status', ['completed', 'cancelled'])
            ->when($exceptOrderId, fn ($q) => $q->whereKeyNot($exceptOrderId))
            ->where(fn ($q) => $q->whereInstant('scheduled_start', '<', $dayEnd)->orWhereNull('scheduled_start'))
            ->where(fn ($q) => $q->whereInstant('scheduled_end', '>', $dayStart)->orWhereNull('scheduled_end'))
            ->get(['scheduled_start', 'scheduled_end']);
        if ($orders->contains(fn ($order) => ! $order->scheduled_start || ! $order->scheduled_end)) {
            throw ValidationException::withMessages(['scheduled_start' => __('An existing assignment has an incomplete schedule. Availability cannot be confirmed.')]);
        }
        $activities = CrmActivity::where('assigned_user_id', $userId)->whereNull('work_order_id')
            ->when($exceptActivityId, fn ($q) => $q->whereKeyNot($exceptActivityId))
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where(fn ($q) => $q->where(fn ($q) => $q->whereInstant('starts_at', '<', $dayEnd)->where(fn ($q) => $q->whereInstant('due_at', '>', $dayStart)->orWhereNull('due_at')))
                ->orWhere(fn ($q) => $q->whereIn('type', ['visit', 'meeting'])->whereNull('starts_at')->whereInstant('due_at', '>=', $dayStart)->whereInstant('due_at', '<', $dayEnd)))
            ->get(['starts_at', 'due_at']);
        if ($activities->contains(fn ($activity) => ! $activity->starts_at || ! $activity->due_at)) {
            throw ValidationException::withMessages(['scheduled_start' => __('An existing assignment has an incomplete schedule. Availability cannot be confirmed.')]);
        }
        if ($profile) {
            for ($day = $dayStart->copy(); $day->lt($end); $day->addDay()) {
                $next = $day->copy()->addDay();
                $minutes = fn ($a, $b) => max(0, $a->max($day)->diffInMinutes($b->min($next), false));
                $occupied = $orders->sum(fn ($o) => $minutes($o->scheduled_start, $o->scheduled_end))
                    + $activities->sum(fn ($a) => $minutes($a->starts_at, $a->due_at));
                $this->dailyLoad[] = ['date' => $day->toDateString(), 'occupied_minutes' => (int) round($occupied), 'proposed_minutes' => (int) round($minutes($start, $end)), 'capacity_minutes' => $profile->daily_capacity_minutes];
                if ($occupied + $minutes($start, $end) > $profile->daily_capacity_minutes) {
                    throw ValidationException::withMessages(['scheduled_start' => __('Assignment exceeds the technician daily capacity of :minutes minutes.', ['minutes' => $profile->daily_capacity_minutes])]);
                }
            }
        }
        $activityConflict = CrmActivity::query()->where('assigned_user_id', $userId)->whereNull('work_order_id')->when($exceptActivityId, fn ($q) => $q->whereKeyNot($exceptActivityId))
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereInstant('starts_at', '<', $end)->whereInstant('due_at', '>', $start)->exists();
        if ($activityConflict) {
            throw ValidationException::withMessages(['scheduled_start' => __('This technician has another scheduled visit or activity during this period.')]);
        }

        $this->assertWorkingHours($profile, $start, $end);

    }

    private function assertWorkingHours(?FieldServiceProfile $profile, $start, $end): void
    {
        if ($profile?->weekly_schedule === null) {
            throw ValidationException::withMessages(['assigned_user_id' => __('Working hours are not configured. Availability cannot be confirmed.')]);
        }
        if ($start->diffInDays($end) > 31) {
            throw ValidationException::withMessages(['scheduled_end' => __('An intervention cannot exceed 31 days.')]);
        }
        for ($day = $start->copy()->startOfDay(); $day->lt($end); $day->addDay()) {
            if (HrCalendarHoliday::whereDate('holiday_date', $day->toDateString())->exists()) {
                throw ValidationException::withMessages(['scheduled_start' => __('The selected period includes a non-working holiday.')]);
            }
            $partStart = $start->max($day);
            $partEnd = $end->min($day->copy()->addDay());
            $covered = false;
            foreach ($profile->weekly_schedule[(string) $day->isoWeekday()] ?? [] as $slot) {
                // Enumerate DST boundaries explicitly; ambiguous or nonexistent boundaries are unsafe.
                $opens = BusinessTime::candidates($day->toDateString().' '.$slot['start']);
                $closes = BusinessTime::candidates($slot['end'] === '24:00' ? $day->copy()->addDay()->toDateString().' 00:00' : $day->toDateString().' '.$slot['end']);
                if (count($opens) === 1 && count($closes) === 1 && $partStart->gte($opens[0]) && $partEnd->lte($closes[0])) {
                    $covered = true;
                    break;
                }
            }
            if (! $covered) {
                throw ValidationException::withMessages(['scheduled_start' => __('The selected period is outside configured working hours.')]);
            }
        }
    }

    /** @param array<int, int|null|string> $userIds */
    public function lockTechnicians(array $userIds): void
    {
        User::query()
            ->whereKey(collect($userIds)->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    /** @return array<int, true> */
    public function conflictIds(Collection $orders): array
    {
        $conflicts = [];
        $orders->whereNotIn('status', ['completed', 'cancelled'])
            ->filter(fn (WorkOrder $order) => $order->assigned_user_id && $order->scheduled_start && $order->scheduled_end)
            ->groupBy('assigned_user_id')
            ->each(function (Collection $technicianOrders) use (&$conflicts): void {
                $items = $technicianOrders->sortBy('scheduled_start')->values();
                foreach ($items as $position => $order) {
                    foreach ($items->slice($position + 1) as $candidate) {
                        if ($candidate->scheduled_start >= $order->scheduled_end) {
                            break;
                        }
                        if ($candidate->scheduled_end > $order->scheduled_start) {
                            $conflicts[$order->id] = true;
                            $conflicts[$candidate->id] = true;
                        }
                    }
                }
            });

        return $conflicts;
    }

    /** @return array<int, array<int, array{type:string,severity:string,message:string}>> */
    public function planningIssues(Collection $orders): array
    {
        $issues = [];
        $conflicts = $this->conflictIds($orders);
        foreach (array_keys($conflicts) as $orderId) {
            $issues[$orderId][] = ['type' => 'overlap', 'severity' => 'critical', 'message' => __('Schedule overlap')];
        }
        $profiles = FieldServiceProfile::whereIn('user_id', $orders->pluck('assigned_user_id')->filter()->unique())->get()->keyBy('user_id');
        $orders->filter(fn (WorkOrder $order) => $order->assigned_user_id && $order->scheduled_start)->groupBy(fn (WorkOrder $order) => $order->assigned_user_id.'-'.$order->scheduled_start->toDateString())
            ->each(function (Collection $dayOrders) use (&$issues, $profiles): void {
                $profile = $profiles->get($dayOrders->first()->assigned_user_id);
                if (! $profile) {
                    return;
                }
                $minutes = $dayOrders->sum(fn (WorkOrder $order) => $order->scheduled_start && $order->scheduled_end ? $order->scheduled_start->diffInMinutes($order->scheduled_end) : ($order->estimated_duration_minutes ?? 0));
                if ($minutes > $profile->daily_capacity_minutes) {
                    foreach ($dayOrders as $order) {
                        $issues[$order->id][] = ['type' => 'capacity', 'severity' => 'warning', 'message' => __('Daily capacity exceeded')];
                    }
                }
                $dayOrders->sortBy('scheduled_start')->values()->each(function (WorkOrder $order, int $index) use ($dayOrders, $profile, &$issues): void {
                    if ($index === 0) {
                        return;
                    }
                    $previous = $dayOrders->sortBy('scheduled_start')->values()->get($index - 1);
                    $distance = $this->distanceKm($previous->installation, $order->installation);
                    if ($distance !== null && $distance > $profile->max_travel_km) {
                        $message = __('Excessive travel: :distance km between consecutive jobs', ['distance' => round($distance)]);
                        $issues[$previous->id][] = ['type' => 'travel', 'severity' => 'warning', 'message' => $message];
                        $issues[$order->id][] = ['type' => 'travel', 'severity' => 'warning', 'message' => $message];
                    }
                });
            });
        foreach ($orders as $order) {
            $profile = $profiles->get($order->assigned_user_id);
            $missing = array_values(array_diff($order->required_skills ?? [], $profile?->skills ?? []));
            if ($missing !== []) {
                $issues[$order->id][] = ['type' => 'skills', 'severity' => 'critical', 'message' => __('Missing skills: :skills', ['skills' => implode(', ', array_map(TechnicianSkills::label(...), $missing))])];
            }
            if ($order->installation?->materialRequirements?->isNotEmpty() && $order->stockReservations?->where('status', 'active')->isEmpty()) {
                $issues[$order->id][] = ['type' => 'materials', 'severity' => 'warning', 'message' => __('Materials pending')];
            }
        }

        return $issues;
    }

    private function distanceKm(mixed $from, mixed $to): ?float
    {
        if (! $from?->site_latitude || ! $from?->site_longitude || ! $to?->site_latitude || ! $to?->site_longitude) {
            return null;
        }
        $earthRadius = 6371;
        $latitudeDelta = deg2rad((float) $to->site_latitude - (float) $from->site_latitude);
        $longitudeDelta = deg2rad((float) $to->site_longitude - (float) $from->site_longitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad((float) $from->site_latitude)) * cos(deg2rad((float) $to->site_latitude)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
