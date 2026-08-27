<?php

namespace App\Services\AfterSales;

use App\Models\ServiceCycleCommitment;
use App\Models\ServiceCycleScheduleRevision;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceCycleScheduleWorkflow
{
    public function __construct(private ServiceCycleCommunicationWorkflow $communications) {}

    public function confirm(ServiceCycleCommitment $commitment, User $actor): ServiceCycleScheduleRevision
    {
        return DB::transaction(function () use ($commitment, $actor): ServiceCycleScheduleRevision {
            $locked = ServiceCycleCommitment::query()->with('workOrder')->lockForUpdate()->findOrFail($commitment->id);
            if (! $locked->workOrder || ! $locked->workOrder->scheduled_start || ! $locked->workOrder->scheduled_end) {
                throw ValidationException::withMessages(['schedule' => __('Materialize and schedule the work order before confirming the customer date.')]);
            }
            $revision = ServiceCycleScheduleRevision::query()
                ->where('service_cycle_commitment_id', $locked->id)
                ->latest('version')->lockForUpdate()->firstOrFail();
            if ($revision->status === 'confirmed') {
                return $revision;
            }
            if ($revision->status !== 'pending_confirmation') {
                throw ValidationException::withMessages(['schedule' => __('Only the latest pending schedule can be confirmed.')]);
            }
            ServiceCycleScheduleRevision::query()
                ->where('service_cycle_commitment_id', $locked->id)
                ->where('status', 'confirmed')
                ->update(['status' => 'superseded', 'superseded_at' => now()]);
            $revision->update(['status' => 'confirmed', 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
            $locked->update(['schedule_status' => 'confirmed', 'schedule_confirmed_by' => $actor->id, 'schedule_confirmed_at' => now()]);

            return $revision->refresh();
        });
    }

    public function recordRevision(WorkOrder $order, string $reason, ?int $actorId): void
    {
        $commitment = $order->loadMissing('serviceTicket.serviceCycleCommitment')->serviceTicket?->serviceCycleCommitment;
        if (! $commitment) {
            return;
        }

        $revision = DB::transaction(function () use ($commitment, $order, $reason, $actorId): ServiceCycleScheduleRevision {
            $locked = ServiceCycleCommitment::query()->lockForUpdate()->findOrFail($commitment->id);
            $version = (int) ServiceCycleScheduleRevision::query()->where('service_cycle_commitment_id', $locked->id)->max('version') + 1;
            ServiceCycleScheduleRevision::query()
                ->where('service_cycle_commitment_id', $locked->id)
                ->where('status', 'confirmed')
                ->update(['status' => 'superseded', 'superseded_at' => now()]);
            $locked->update(['schedule_status' => 'revision_required']);

            return $locked->scheduleRevisions()->create([
                'work_order_id' => $order->id,
                'version' => $version,
                'status' => 'pending_confirmation',
                'scheduled_start' => $order->scheduled_start,
                'scheduled_end' => $order->scheduled_end,
                'assigned_user_id' => $order->assigned_user_id,
                'reason' => trim($reason),
                'created_by' => $actorId,
            ]);
        });

        $actor = $actorId ? User::find($actorId) : null;
        if ($actor) {
            $this->communications->reviseMaintenanceSchedule($commitment, $revision->scheduled_start->toISOString(), $reason, $actor);
        }
    }
}
