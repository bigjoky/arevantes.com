<?php

namespace App\Services\Work;

use App\Models\Installation;
use App\Models\WorkOrder;
use App\Services\AfterSales\ServiceCycleCompletionWorkflow;
use App\Support\Time\BusinessTime;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderStateManager
{
    public function __construct(
        private WorkOrderWorkflow $workflow,
        private WorkOrderCompletionGuard $completion,
        private InstallationWorkOrderSynchronizer $installations,
        private WorkOrderPlanningReadiness $readiness,
        private ServiceCycleCompletionWorkflow $serviceCycles,
    ) {}

    public function transition(
        WorkOrder $order,
        string $status,
        ?string $completionNotes = null,
        ?string $blockedReason = null,
        ?string $expectedUpdatedAt = null,
        ?int $actorId = null,
    ): WorkOrder {
        return DB::transaction(function () use ($order, $status, $completionNotes, $blockedReason, $expectedUpdatedAt, $actorId): WorkOrder {
            $serviceCycle = $status === 'completed' ? $this->serviceCycles->lockContext($order->id) : null;
            Installation::whereKey($order->installation_id)->lockForUpdate()->firstOrFail();
            $locked = WorkOrder::query()->with(['template', 'installation'])->lockForUpdate()->findOrFail($order->id);
            if ($expectedUpdatedAt !== null && ! $locked->updated_at->equalTo(Carbon::parse($expectedUpdatedAt))) {
                $locked->loadMissing(['assignedUser:id,name', 'activities:id,work_order_id,priority']);
                $planning = $this->readiness->for($locked);
                throw new HttpResponseException(response()->json([
                    'message' => __('This work order changed on the server. Refresh it before retrying.'),
                    'conflict' => [
                        'work_order_id' => $locked->id,
                        'expected_updated_at' => $expectedUpdatedAt,
                        'current_updated_at' => $locked->updated_at->toISOString(),
                        'current' => [
                            'number' => $locked->number,
                            'title' => $locked->title,
                            'status' => $locked->status,
                            'scheduled_start' => $locked->scheduled_start?->toISOString(),
                            'scheduled_end' => $locked->scheduled_end?->toISOString(),
                            'assigned_user' => $locked->assignedUser?->only(['id', 'name']),
                            'priority' => $locked->activities->first()?->priority ?? ($locked->kind === 'repair' ? 'high' : 'normal'),
                            'material_ready' => $planning['material_ready'],
                            'material_ready_now' => $planning['material_ready_now'],
                            'material_ready_on' => $planning['material_ready_on'],
                            'updated_at' => $locked->updated_at->toISOString(),
                        ],
                    ],
                ], 409));
            }
            $this->workflow->assertTransition($locked, $status);
            if (in_array($status, ['in_progress', 'completed'], true) && in_array($locked->kind, ['installation', 'commissioning'], true)) {
                if (! $locked->assigned_user_id) {
                    throw ValidationException::withMessages(['status' => 'Asigna un técnico cualificado antes de iniciar o completar la instalación.']);
                }
                $start = $locked->scheduled_start ?: BusinessTime::now();
                $end = $locked->scheduled_end ?: $start->copy()->addMinutes($locked->estimated_duration_minutes ?: 120);
                app(TechnicianSchedule::class)->assertAvailable($locked->assigned_user_id, $start, $end, $locked->id, $locked->required_skills ?? [], $locked->service_zone);
            }
            if ($status === 'in_progress') {
                $readiness = $this->readiness->for($locked);
                abort_if(! $readiness['material_ready_now'], 422, __('Required materials are not physically available for this work yet.'));
                abort_if($locked->assigned_user_id && ! $readiness['personnel_ready'], 422, __('The assigned technician is not qualified or available for this work.'));
            }
            if ($status === 'completed') {
                $this->completion->assertCanComplete($locked, $completionNotes);
                $this->serviceCycles->assertCanClose($serviceCycle, $locked);
            }

            $updates = [
                'status' => $status,
                'completed_at' => $status === 'completed' ? ($locked->completed_at ?: BusinessTime::now()) : null,
            ];
            if ($completionNotes !== null || $status === 'completed') {
                $updates['completion_notes'] = $completionNotes;
            }
            $locked->update($updates);
            $activityStatus = match ($status) {
                'completed' => 'completed',
                'cancelled' => 'cancelled',
                'blocked' => 'blocked',
                'planned' => 'pending',
                default => 'in_progress',
            };
            $locked->activities()->update([
                'status' => $activityStatus,
                'blocked_reason' => $status === 'blocked' ? $blockedReason : null,
                'completed_at' => $status === 'completed' ? $locked->completed_at : null,
            ]);
            $this->installations->sync($locked);
            if ($status === 'completed') {
                $this->serviceCycles->closeLocked($serviceCycle, $locked, $actorId);
            }

            return $locked->fresh(['installation', 'template']);
        });
    }
}
