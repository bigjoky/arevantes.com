<?php

namespace App\Services\Work;

use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkOrderRescheduler
{
    public function __construct(private TechnicianSchedule $schedule) {}

    /** @param array<string, mixed> $changes */
    public function reschedule(WorkOrder $order, array $changes, string $reason, ?int $userId, ?string $batchUuid = null): void
    {
        DB::transaction(function () use ($order, $changes, $reason, $userId, $batchUuid): void {
            $lockedOrder = WorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            $newOwner = array_key_exists('assigned_user_id', $changes) ? $changes['assigned_user_id'] : $lockedOrder->assigned_user_id;
            $this->schedule->lockTechnicians([$lockedOrder->assigned_user_id, $newOwner]);
            $this->rescheduleLocked($lockedOrder, $changes, $reason, $userId, $batchUuid);
        });
        $order->refresh();
    }

    /** @param array<int, array{work_order_id:int, scheduled_start:string, scheduled_end:string, assigned_user_id?:int|null}> $items */
    public function bulk(array $items, string $reason, ?int $userId): string
    {
        $batch = (string) Str::uuid();
        DB::transaction(function () use ($items, $reason, $userId, $batch): void {
            $orderIds = collect($items)->pluck('work_order_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            abort_unless($orderIds->count() === count($items), 422, __('A work order can only appear once in a rescheduling batch.'));
            $orders = WorkOrder::query()->whereKey($orderIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($orders->count() === $orderIds->count(), 404);
            $owners = collect($items)->pluck('assigned_user_id')->merge($orders->pluck('assigned_user_id'))->all();
            $this->schedule->lockTechnicians($owners);

            foreach ($items as $item) {
                $order = $orders->get((int) $item['work_order_id']);
                $changes = collect($item)->except('work_order_id')->all();
                $changes['scheduled_start'] = Carbon::parse($changes['scheduled_start']);
                $changes['scheduled_end'] = Carbon::parse($changes['scheduled_end']);
                $this->rescheduleLocked($order, $changes, $reason, $userId, $batch);
            }
        });

        return $batch;
    }

    /** @param array<string, mixed> $changes */
    private function rescheduleLocked(WorkOrder $order, array $changes, string $reason, ?int $userId, ?string $batchUuid): void
    {
        $newStart = $changes['scheduled_start'] ?? $order->scheduled_start;
        $newEnd = $changes['scheduled_end'] ?? $order->scheduled_end;
        $newOwner = array_key_exists('assigned_user_id', $changes) ? $changes['assigned_user_id'] : $order->assigned_user_id;
        $this->schedule->assertAvailable(
            $newOwner ? (int) $newOwner : null,
            $newStart,
            $newEnd,
            $order->id,
            $order->required_skills ?? [],
            $order->service_zone,
        );

        $order->reschedules()->create([
            'changed_by' => $userId,
            'old_assigned_user_id' => $order->assigned_user_id,
            'new_assigned_user_id' => $newOwner,
            'old_scheduled_start' => $order->scheduled_start,
            'old_scheduled_end' => $order->scheduled_end,
            'new_scheduled_start' => $newStart,
            'new_scheduled_end' => $newEnd,
            'batch_uuid' => $batchUuid,
            'reason' => $reason,
        ]);
        $order->update($changes);
        $order->activities()->update(['assigned_user_id' => $newOwner, 'starts_at' => $newStart, 'due_at' => $newEnd]);
    }
}
