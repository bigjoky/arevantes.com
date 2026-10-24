<?php

namespace App\Services\Engineering;

use App\Models\EngineeringScheduleProposal;
use App\Models\FieldServiceProfile;
use App\Models\HrEmploymentProfile;
use App\Models\HrLeaveRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Work\TechnicianSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TechnicalScheduleAvailability
{
    public function check(EngineeringScheduleProposal $proposal, string $startTime): array
    {
        $start = Carbon::parse($proposal->proposed_on->toDateString().' '.$startTime, config('app.timezone'));
        $end = $start->copy()->addMinutes((int) round((float) $proposal->estimated_hours * 60));
        $issues = [];
        $warnings = [];
        if ($start->isPast()) {
            $issues[] = __('The proposed time has already passed.');
        }
        if ($end->gt($start->copy()->startOfDay()->addDay())) {
            $issues[] = __('The proposed work must fit within one calendar day.');
        }
        $user = User::findOrFail($proposal->assignee_id);
        $profile = FieldServiceProfile::where('user_id', $user->id)->first();
        if (! $profile || $profile->daily_capacity_minutes <= 0) {
            $issues[] = __('Configure field-service capacity before confirming availability.');
        }
        $dayStart = $start->copy()->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();
        $orders = WorkOrder::where('assigned_user_id', $user->id)->whereNotIn('status', ['completed', 'cancelled'])
            ->where('scheduled_start', '<', $dayEnd)
            ->where(fn ($query) => $query->where('scheduled_end', '>', $dayStart)->orWhereNull('scheduled_end'))
            ->get(['scheduled_start', 'scheduled_end']);
        if ($orders->contains(fn ($order) => $order->scheduled_end === null)) {
            $issues[] = __('An existing assignment has no end time. Complete its schedule first.');
        }
        $occupied = $orders->filter(fn ($order) => $order->scheduled_end !== null)->sum(fn ($order) => max(0, $order->scheduled_start->max($dayStart)->diffInMinutes($order->scheduled_end->min($dayEnd), false)));
        if ($profile && $occupied + $start->diffInMinutes($end) > $profile->daily_capacity_minutes) {
            $issues[] = __('Assignment exceeds the technician daily capacity of :minutes minutes.', ['minutes' => $profile->daily_capacity_minutes]);
        }
        if ($user->person_id) {
            $employment = HrEmploymentProfile::where('person_id', $user->person_id)->first();
            if ($employment && ($employment->status !== 'active'
                || ($employment->start_date && $employment->start_date->gt($start->copy()->startOfDay()))
                || ($employment->end_date && $employment->end_date->lt($start->copy()->startOfDay())))) {
                $issues[] = __('The responsible person has no active employment for this date.');
            }
            $leaves = HrLeaveRequest::where('person_id', $user->person_id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_date', '<=', $end->copy()->subSecond()->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString())
                ->pluck('status');
            if ($leaves->contains('approved')) {
                $issues[] = __('The responsible person has an approved absence during this period.');
            }
            if ($leaves->contains('pending')) {
                $warnings[] = __('A pending absence request may affect this period.');
            }
        }
        try {
            app(TechnicianSchedule::class)->assertAvailable($user->id, $start, $end, allowPendingAbsenceWarning: true);
        } catch (ValidationException $exception) {
            $issues = [...$issues, ...array_merge(...array_values($exception->errors()))];
        }

        return [
            'status' => $issues === [] ? ($warnings === [] ? 'clear' : 'warning') : 'blocked',
            'checked_at' => now()->toIso8601String(), 'timezone' => config('app.timezone'),
            'start' => $start->toIso8601String(), 'end' => $end->toIso8601String(),
            'issues' => array_values(array_unique($issues)), 'warnings' => $warnings,
        ];
    }
}
