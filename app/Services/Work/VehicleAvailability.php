<?php

namespace App\Services\Work;

use App\Models\CrmActivity;
use App\Models\OperationalVehicle;
use App\Models\WorkOrder;
use App\Support\Time\BusinessTime;
use Illuminate\Validation\ValidationException;

class VehicleAvailability
{
    /** Acquire after installation, work order and technician locks, sorted across bulk operations. */
    public function lockVehicles(array $ids): void
    {
        OperationalVehicle::whereKey(collect($ids)->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values())
            ->orderBy('id')->lockForUpdate()->get(['id']);
    }

    public function decision(?int $id, mixed $start, mixed $end, ?int $exceptOrderId = null, bool $required = false, ?int $exceptActivityId = null): array
    {
        try {
            $this->assertAvailable($id, $start, $end, $exceptOrderId, $required, $exceptActivityId);

            return ['status' => $id ? 'available' : 'not_required', 'issues' => []];
        } catch (ValidationException $e) {
            $issues = array_merge(...array_values($e->errors()));
            $unknown = $id && (! $start || ! $end) && OperationalVehicle::whereKey($id)->value('status') === 'available';

            return ['status' => $unknown ? 'unknown' : 'unavailable', 'issues' => $issues];
        }
    }

    public function assertAvailable(?int $id, mixed $start, mixed $end, ?int $exceptOrderId = null, bool $required = false, ?int $exceptActivityId = null): void
    {
        if (! $id) {
            if ($required) {
                throw ValidationException::withMessages(['vehicle_id' => __('Select a vehicle required for this intervention.')]);
            }

            return;
        }
        $vehicle = OperationalVehicle::findOrFail($id);
        if ($vehicle->status !== 'available') {
            throw ValidationException::withMessages(['vehicle_id' => __('This vehicle is in maintenance or out of service.')]);
        }
        if (! $start || ! $end) {
            throw ValidationException::withMessages(['vehicle_id' => __('A vehicle assignment requires a complete schedule.')]);
        }
        $start = BusinessTime::parse($start, 'scheduled_start');
        $end = BusinessTime::parse($end, 'scheduled_end');
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['scheduled_end' => __('The scheduled end must be after the scheduled start.')]);
        }
        if ($vehicle->blocks()->whereNull('cancelled_at_utc')->where('starts_at_utc', '<', $end->copy()->utc())->where('ends_at_utc', '>', $start->copy()->utc())->exists()) {
            throw ValidationException::withMessages(['vehicle_id' => __('This vehicle is blocked during the selected period.')]);
        }
        $conflict = WorkOrder::where('vehicle_id', $id)->whereNotIn('status', ['completed', 'cancelled'])
            ->when($exceptOrderId, fn ($q) => $q->whereKeyNot($exceptOrderId))
            ->whereInstant('scheduled_start', '<', $end)->whereInstant('scheduled_end', '>', $start)->exists();
        $activityConflict = CrmActivity::where('vehicle_id', $id)->whereNull('work_order_id')->whereNotIn('status', ['completed', 'cancelled'])
            ->when($exceptActivityId, fn ($q) => $q->whereKeyNot($exceptActivityId))
            ->whereInstant('starts_at', '<', $end)->whereInstant('due_at', '>', $start)->exists();
        $incomplete = CrmActivity::where('vehicle_id', $id)->whereNull('work_order_id')->whereNotIn('status', ['completed', 'cancelled'])
            ->when($exceptActivityId, fn ($q) => $q->whereKeyNot($exceptActivityId))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereNull('due_at'))->exists();
        if ($incomplete) {
            throw ValidationException::withMessages(['vehicle_id' => __('An existing vehicle assignment has an incomplete schedule.')]);
        }
        if ($conflict || $activityConflict) {
            throw ValidationException::withMessages(['vehicle_id' => __('This vehicle is already assigned during the selected period.')]);
        }
    }
}
