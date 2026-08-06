<?php

namespace App\Services\HumanResources;

use App\Models\HrLeaveRequest;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;

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
}
