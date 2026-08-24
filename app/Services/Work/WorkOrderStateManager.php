<?php

namespace App\Services\Work;

use App\Models\WorkOrder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkOrderStateManager
{
    public function __construct(
        private WorkOrderWorkflow $workflow,
        private WorkOrderCompletionGuard $completion,
        private InstallationWorkOrderSynchronizer $installations,
        private WorkOrderPlanningReadiness $readiness,
    ) {}

    public function transition(
        WorkOrder $order,
        string $status,
        ?string $completionNotes = null,
        ?string $blockedReason = null,
        ?string $expectedUpdatedAt = null,
    ): WorkOrder {
        return DB::transaction(function () use ($order, $status, $completionNotes, $blockedReason, $expectedUpdatedAt): WorkOrder {
            $locked = WorkOrder::query()->with(['template', 'installation'])->lockForUpdate()->findOrFail($order->id);
            if ($expectedUpdatedAt !== null && ! $locked->updated_at->equalTo(Carbon::parse($expectedUpdatedAt))) {
                throw new HttpResponseException(response()->json([
                    'message' => __('This work order changed on the server. Refresh it before retrying.'),
                    'conflict' => [
                        'work_order_id' => $locked->id,
                        'expected_updated_at' => $expectedUpdatedAt,
                        'current_updated_at' => $locked->updated_at->toISOString(),
                    ],
                ], 409));
            }
            $this->workflow->assertTransition($locked, $status);
            if ($status === 'in_progress') {
                $readiness = $this->readiness->for($locked);
                abort_if(! $readiness['material_ready_now'], 422, __('Required materials are not physically available for this work yet.'));
                abort_if($locked->assigned_user_id && ! $readiness['personnel_ready'], 422, __('The assigned technician is not qualified or available for this work.'));
            }
            if ($status === 'completed') {
                $this->completion->assertCanComplete($locked, $completionNotes);
            }

            $updates = [
                'status' => $status,
                'completed_at' => $status === 'completed' ? ($locked->completed_at ?: now()) : null,
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

            return $locked->fresh(['installation', 'template']);
        });
    }
}
