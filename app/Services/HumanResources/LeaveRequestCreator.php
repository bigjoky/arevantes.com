<?php

namespace App\Services\HumanResources;

use App\Models\HrLeavePolicy;
use App\Models\HrLeaveRequest;
use App\Models\Person;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class LeaveRequestCreator
{
    /**
     * @param  array{type: string, start_date: string, end_date: string, notes?: ?string, attachment?: mixed}  $data
     */
    public function create(Person $person, array $data): HrLeaveRequest
    {
        $policy = HrLeavePolicy::query()->where('type', $data['type'])->where('is_active', true)->first();
        if (! $policy) {
            throw ValidationException::withMessages(['type' => __('This leave policy is not active.')]);
        }

        if ($policy->requires_attachment && empty($data['attachment'])) {
            throw ValidationException::withMessages(['attachment' => __('This leave type requires supporting evidence.')]);
        }

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

        $attachment = $data['attachment'] ?? null;
        unset($data['attachment']);

        $leave = $person->leaveRequests()->create([
            ...$data,
            'requested_days' => CarbonImmutable::parse($data['start_date'])
                ->diffInDays(CarbonImmutable::parse($data['end_date'])) + 1,
            'status' => 'pending',
        ]);

        if ($attachment) {
            $path = $attachment->store("human-resources/leave/{$leave->id}", config('filesystems.private'));
            $leave->attachments()->create([
                'uploaded_by' => auth()->id(),
                'original_name' => $attachment->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $attachment->getMimeType() ?: 'application/octet-stream',
                'size' => $attachment->getSize(),
            ]);
        }

        return $leave;
    }
}
