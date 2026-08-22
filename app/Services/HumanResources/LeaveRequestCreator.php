<?php

namespace App\Services\HumanResources;

use App\Models\HrLeavePolicy;
use App\Models\HrLeaveRequest;
use App\Models\Person;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LeaveRequestCreator
{
    public function __construct(
        private LeaveDayCalculator $days,
        private LeaveBalanceCalculator $balances,
    ) {}

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

        $attachment = $data['attachment'] ?? null;
        unset($data['attachment']);
        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        $storedPath = null;

        try {
            return DB::transaction(function () use ($person, $policy, $data, $attachment, $start, $end, &$storedPath) {
                Person::query()->whereKey($person->id)->lockForUpdate()->firstOrFail();
                $overlaps = $person->leaveRequests()
                    ->whereIn('status', ['pending', 'approved'])
                    ->whereDate('start_date', '<=', $end)
                    ->whereDate('end_date', '>=', $start)
                    ->exists();
                if ($overlaps) {
                    throw ValidationException::withMessages([
                        'start_date' => __('The selected dates overlap an existing leave request.'),
                    ]);
                }

                $this->balances->assertRequestable($person, $policy, $start, $end);
                $leave = $person->leaveRequests()->create([
                    ...$data,
                    'requested_days' => $this->days->count($start, $end, $policy->day_count_method),
                    'status' => 'pending',
                ]);

                if ($attachment) {
                    $storedPath = $attachment->store("human-resources/leave/{$leave->id}", config('filesystems.private'));
                    $leave->attachments()->create([
                        'uploaded_by' => auth()->id(),
                        'original_name' => $attachment->getClientOriginalName(),
                        'path' => $storedPath,
                        'mime_type' => $attachment->getMimeType() ?: 'application/octet-stream',
                        'size' => $attachment->getSize(),
                    ]);
                }

                return $leave;
            });
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk(config('filesystems.private'))->delete($storedPath);
            }
            throw $exception;
        }
    }
}
