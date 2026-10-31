<?php

namespace App\Services\Operations;

use App\Models\CrmActivity;
use App\Models\FieldServiceProfile;
use App\Models\HrCalendarHoliday;
use App\Models\HrLeaveRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Time\BusinessTime;
use Illuminate\Http\Request;

class IndividualCalendar
{
    public function __construct(private OperationsWorkspace $workspace) {}

    public function for(Request $request, User $user): array
    {
        $request->merge(['owner' => $user->id]);
        [$filters, $start, $end] = $this->workspace->range($request);
        $agenda = $this->workspace->agenda($request);
        $staffLinked = $user->staffProfile?->category === 'staff';
        $profile = FieldServiceProfile::where('user_id', $user->id)->first();
        $holidays = HrCalendarHoliday::whereDate('holiday_date', '>=', $start->toDateString())->whereDate('holiday_date', '<=', $end->toDateString())->get(['holiday_date', 'name'])->keyBy(fn ($h) => $h->holiday_date->toDateString());
        $leaves = $user->person_id ? HrLeaveRequest::where('person_id', $user->person_id)->whereIn('status', ['approved', 'pending'])->whereDate('start_date', '<=', $end->toDateString())->whereDate('end_date', '>=', $start->toDateString())->get(['start_date', 'end_date', 'status', 'type']) : collect();
        $incompleteOrders = WorkOrder::where('assigned_user_id', $user->id)->whereNotIn('status', ['completed', 'cancelled'])->where(fn ($q) => $q->whereNull('scheduled_start')->orWhereNull('scheduled_end'))->get(['scheduled_start', 'scheduled_end']);
        $incompleteActivities = CrmActivity::where('assigned_user_id', $user->id)->whereNull('work_order_id')->whereNotIn('status', ['completed', 'cancelled'])->where(fn ($q) => $q->whereNull('starts_at')->orWhereNull('due_at'))->get(['starts_at', 'due_at']);
        $now = BusinessTime::now();
        $events = $agenda['orders']->filter(fn ($o) => $o->status !== 'cancelled' && ($o->status !== 'completed' || ($o->scheduled_end && $o->scheduled_end->lte($now))))
            ->map(fn ($o) => [$o->scheduled_start, $o->scheduled_end])
            ->concat($agenda['activities']->filter(fn ($a) => $a->status !== 'cancelled' && ($a->status !== 'completed' || ($a->due_at && $a->due_at->lte($now))))->map(fn ($a) => [$a->starts_at, $a->due_at]));
        $days = [];
        for ($day = $start->startOfDay(); $day <= $end; $day = $day->addDay()) {
            $date = $day->toDateString();
            $leave = $leaves->first(fn ($l) => $l->start_date->toDateString() <= $date && $l->end_date->toDateString() >= $date);
            $holiday = $holidays->get($date);
            $slots = $profile?->weekly_schedule === null ? null : ($profile->weekly_schedule[(string) $day->isoWeekday()] ?? []);
            $intervals = [];
            $known = $slots !== null;
            foreach ($slots ?? [] as $slot) {
                $opens = BusinessTime::candidates($date.' '.$slot['start']);
                $closes = BusinessTime::candidates($slot['end'] === '24:00' ? $day->addDay()->toDateString().' 00:00' : $date.' '.$slot['end']);
                if (count($opens) !== 1 || count($closes) !== 1) {
                    $known = false;

                    continue;
                }
                $intervals[] = [$opens[0], $closes[0]];
            }
            $occupied = 0;
            $capacity = 0;
            $dayStart = BusinessTime::parse($date.' 00:00');
            $dayEnd = BusinessTime::parse($day->addDay()->toDateString().' 00:00');
            $allIntervals = $events->filter(fn ($event) => $event[0] && $event[1] && $event[0]->lt($dayEnd) && $event[1]->gt($dayStart))
                ->map(fn ($event) => [max($dayStart->timestamp, $event[0]->timestamp), min($dayEnd->timestamp, $event[1]->timestamp)])->sortBy(fn ($event) => $event[0])->values();
            $totalOccupied = 0;
            $lastEnd = null;
            foreach ($allIntervals as [$a, $b]) {
                $totalOccupied += max(0, $b - max($a, $lastEnd ?? $a)) / 60;
                $lastEnd = max($lastEnd ?? $b, $b);
            }

            foreach ($intervals as [$open, $close]) {
                $capacity += $open->diffInMinutes($close);
                $clipped = $events->filter(fn ($event) => $event[0] && $event[1] && $event[0]->lt($close) && $event[1]->gt($open))
                    ->map(fn ($event) => [max($open->timestamp, $event[0]->timestamp), min($close->timestamp, $event[1]->timestamp)])->sortBy(fn ($event) => $event[0])->values();
                $lastEnd = null;
                foreach ($clipped as [$a, $b]) {
                    $occupied += max(0, $b - max($a, $lastEnd ?? $a)) / 60;
                    $lastEnd = max($lastEnd ?? $b, $b);
                }
            }
            $incomplete = $incompleteOrders->contains(fn ($order) => (! $order->scheduled_start || $order->scheduled_start->lt($dayEnd)) && (! $order->scheduled_end || $order->scheduled_end->gt($dayStart)))
                || $incompleteActivities->contains(fn ($activity) => (! $activity->starts_at || $activity->starts_at->lt($dayEnd)) && (! $activity->due_at || $activity->due_at->gt($dayStart)));
            $dailyCapacity = $profile?->daily_capacity_minutes;
            $blocked = (bool) $holiday || $leave?->status === 'approved';
            $days[] = ['date' => $date, 'slots' => $slots, 'holiday' => $holiday?->name,
                'absence' => $leave?->status, 'vacation' => $leave && ($request->user()->is($user) || $request->user()->can('view-sensitive-hr')) ? $leave->type === 'vacation' : null, 'occupied_minutes' => $incomplete ? null : (int) round($totalOccupied),
                'available_minutes' => $blocked ? 0 : (! $known || $incomplete || ! $staffLinked || ! $dailyCapacity || $leave?->status === 'pending' ? null : (int) round(min(max(0, $capacity - $occupied), max(0, $dailyCapacity - $totalOccupied)))),
                'incomplete' => $incomplete, 'conflict' => ($blocked && $totalOccupied > 0) || ($known && $totalOccupied > $occupied)];
        }

        return [...$agenda, 'calendarUser' => $user->only(['id', 'name']), 'calendarDays' => $days, 'filters' => $filters];
    }
}
