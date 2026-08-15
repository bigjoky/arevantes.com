<?php

namespace App\Services\Work;

use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class WorkOrderStateManager
{
    public function __construct(
        private WorkOrderWorkflow $workflow,
        private WorkOrderCompletionGuard $completion,
        private InstallationWorkOrderSynchronizer $installations,
    ) {}

    public function transition(WorkOrder $order, string $status, ?string $completionNotes = null, ?string $blockedReason = null): WorkOrder
    {
        return DB::transaction(function () use ($order, $status, $completionNotes, $blockedReason): WorkOrder {
            $locked = WorkOrder::query()->with(['template', 'installation'])->lockForUpdate()->findOrFail($order->id);
            $this->workflow->assertTransition($locked, $status);
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
