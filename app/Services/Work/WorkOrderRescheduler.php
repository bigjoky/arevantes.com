<?php

namespace App\Services\Work;

use App\Models\Installation;
use App\Models\WorkOrder;
use App\Services\AfterSales\ServiceCycleScheduleWorkflow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkOrderRescheduler
{
    public function __construct(private TechnicianSchedule $schedule, private ServiceCycleScheduleWorkflow $serviceCycles) {}

    /** @param array<string, mixed> $changes */
    public function reschedule(WorkOrder $order, array $changes, string $reason, ?int $userId, ?string $batchUuid = null): void
    {
        DB::transaction(function () use ($order, $changes, $reason, $userId, $batchUuid): void {
            Installation::whereKey($order->installation_id)->lockForUpdate()->firstOrFail();
            $lockedOrder = WorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (isset($changes['expected_updated_at']) && ! $lockedOrder->updated_at->equalTo(Carbon::parse($changes['expected_updated_at']))) {
                throw ValidationException::withMessages(['expected_updated_at' => __('This work order changed on the server. Refresh it before retrying.')]);
            }
            unset($changes['expected_updated_at']);
            $newOwner = array_key_exists('assigned_user_id', $changes) ? $changes['assigned_user_id'] : $lockedOrder->assigned_user_id;
            $this->schedule->lockTechnicians([$lockedOrder->assigned_user_id, $newOwner]);
            $this->rescheduleLocked($lockedOrder, $changes, $reason, $userId, $batchUuid);
        });
        $order->refresh();
        $this->serviceCycles->recordRevision($order, $reason, $userId);
    }

    /** @param array<int, array{work_order_id:int, scheduled_start:string, scheduled_end:string, assigned_user_id?:int|null}> $items */
    public function bulk(array $items, string $reason, ?int $userId): string
    {
        $batch = (string) Str::uuid();
        DB::transaction(function () use ($items, $reason, $userId, $batch): void {
            $orderIds = collect($items)->pluck('work_order_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            abort_unless($orderIds->count() === count($items), 422, __('A work order can only appear once in a rescheduling batch.'));
            $installationIds = WorkOrder::whereKey($orderIds)->pluck('installation_id')->unique()->sort()->values();
            Installation::whereKey($installationIds)->orderBy('id')->lockForUpdate()->get(['id']);
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

        WorkOrder::query()->whereKey(collect($items)->pluck('work_order_id'))->get()
            ->each(fn (WorkOrder $order) => $this->serviceCycles->recordRevision($order, $reason, $userId));

        return $batch;
    }

    /** @param array<string, mixed> $changes */
    private function rescheduleLocked(WorkOrder $order, array $changes, string $reason, ?int $userId, ?string $batchUuid): void
    {
        abort_if($order->loadMissing('installation')->installation->dossier_closed_at && in_array($order->kind, ['installation', 'commissioning'], true), 422, 'Reabre el expediente antes de reprogramar la ejecución.');
        $newStart = $changes['scheduled_start'] ?? $order->scheduled_start;
        $newEnd = $changes['scheduled_end'] ?? $order->scheduled_end;
        $newStart = $newStart ? Carbon::parse($newStart) : null;
        $newEnd = $newEnd ? Carbon::parse($newEnd) : null;
        $newOwner = array_key_exists('assigned_user_id', $changes) ? $changes['assigned_user_id'] : $order->assigned_user_id;
        $commitment = $order->loadMissing('serviceTicket.serviceCycleCommitment')->serviceTicket?->serviceCycleCommitment;
        if ($commitment && $newStart && ($newStart->toDateString() < $commitment->period_starts_on->toDateString() || $newStart->toDateString() > $commitment->period_ends_on->toDateString())) {
            throw ValidationException::withMessages(['scheduled_start' => __('The visit must remain within its approved service cycle.')]);
        }
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
