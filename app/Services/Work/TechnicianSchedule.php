<?php

namespace App\Services\Work;

use App\Models\FieldServiceProfile;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\TechnicianSkills;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TechnicianSchedule
{
    /** @param array<int, string> $requiredSkills */
    public function assertAvailable(?int $userId, mixed $start, mixed $end, ?int $exceptOrderId = null, array $requiredSkills = [], ?string $serviceZone = null): void
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

        $start = Carbon::parse($start);
        $end = Carbon::parse($end);
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['scheduled_end' => __('The scheduled end must be after the scheduled start.')]);
        }

        $conflict = WorkOrder::query()
            ->where('assigned_user_id', $userId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->when($exceptOrderId, fn ($query) => $query->where('id', '!=', $exceptOrderId))
            ->whereNotNull('scheduled_start')
            ->whereNotNull('scheduled_end')
            ->where('scheduled_start', '<', $end)
            ->where('scheduled_end', '>', $start)
            ->first(['id', 'number', 'scheduled_start', 'scheduled_end']);

        if ($conflict) {
            throw ValidationException::withMessages([
                'scheduled_start' => __('This technician is already assigned to :number from :start to :end.', [
                    'number' => $conflict->number,
                    'start' => $conflict->scheduled_start->format('d/m/Y H:i'),
                    'end' => $conflict->scheduled_end->format('d/m/Y H:i'),
                ]),
            ]);
        }

        if ($profile) {
            $plannedMinutes = (int) WorkOrder::query()->where('assigned_user_id', $userId)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->when($exceptOrderId, fn ($query) => $query->where('id', '!=', $exceptOrderId))
                ->whereDate('scheduled_start', $start->toDateString())
                ->get(['scheduled_start', 'scheduled_end', 'estimated_duration_minutes'])
                ->sum(fn (WorkOrder $order) => $order->scheduled_start && $order->scheduled_end
                    ? $order->scheduled_start->diffInMinutes($order->scheduled_end)
                    : ($order->estimated_duration_minutes ?? 0));
            $newMinutes = $start->diffInMinutes($end);
            if ($plannedMinutes + $newMinutes > $profile->daily_capacity_minutes) {
                throw ValidationException::withMessages(['scheduled_start' => __('Assignment exceeds the technician daily capacity of :minutes minutes.', ['minutes' => $profile->daily_capacity_minutes])]);
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
