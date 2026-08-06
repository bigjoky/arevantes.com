<?php

namespace App\Services\HumanResources;

use App\Models\HrLeaveRequest;
use App\Models\Person;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class LeaveRequestCreator
{
    /**
     * @param  array{type: string, start_date: string, end_date: string, notes?: ?string}  $data
     */
    public function create(Person $person, array $data): HrLeaveRequest
    {
        $overlaps = $person->leaveRequests()
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_date' => __('The selected dates overlap an existing leave request.'),
            ]);
        }

        return $person->leaveRequests()->create([
            ...$data,
            'requested_days' => CarbonImmutable::parse($data['start_date'])
                ->diffInDays(CarbonImmutable::parse($data['end_date'])) + 1,
            'status' => 'pending',
        ]);
    }
}
