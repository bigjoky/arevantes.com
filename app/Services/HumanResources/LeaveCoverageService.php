<?php

namespace App\Services\HumanResources;

use App\Models\HrLeaveRequest;
use App\Models\Person;
use App\Models\WorkOrder;
use App\Services\Work\WorkOrderRescheduler;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LeaveCoverageService
{
    /** @return Collection<int, WorkOrder> */
    public function conflicts(HrLeaveRequest $leave): Collection
    {
        $userId = $leave->person->user?->id;
        if (! $userId) {
            return collect();
        }

        return WorkOrder::query()
            ->where('assigned_user_id', $userId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where('scheduled_start', '<=', $leave->end_date->endOfDay())
            ->where(function ($query) use ($leave) {
                $query->whereNull('scheduled_end')
                    ->orWhere('scheduled_end', '>=', $leave->start_date->startOfDay());
            })
            ->orderBy('scheduled_start')
            ->get();
    }

    public function assertSubstituteAvailable(Person $substitute, HrLeaveRequest $leave): void
    {
        if (! $substitute->user) {
            throw ValidationException::withMessages([
                'substitute_person_id' => __('The substitute needs an active system account before work can be reassigned.'),
            ]);
        }
        if ($substitute->employmentProfile && $substitute->employmentProfile->status !== 'active') {
            throw ValidationException::withMessages([
                'substitute_person_id' => __('The selected substitute does not have an active employment profile.'),
            ]);
        }
        $overlaps = $substitute->leaveRequests()
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $leave->end_date)
            ->whereDate('end_date', '>=', $leave->start_date)
            ->exists();
        if ($overlaps) {
            throw ValidationException::withMessages([
                'substitute_person_id' => __('The selected substitute is not available for the full absence period.'),
            ]);
        }
    }

    /** @param Collection<int, WorkOrder> $orders */
    public function reassign(Collection $orders, Person $substitute): void
    {
        if ($orders->isEmpty()) {
            return;
        }
        app(WorkOrderRescheduler::class)->bulk($orders->map(fn (WorkOrder $order) => [
            'work_order_id' => $order->id,
            'assigned_user_id' => $substitute->user->id,
            'scheduled_start' => $order->scheduled_start?->toIso8601String(),
            'scheduled_end' => $order->scheduled_end?->toIso8601String(),
        ])->all(), __('Reassignment for an approved absence.'), auth()->id());
    }
}
