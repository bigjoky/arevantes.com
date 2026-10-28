<?php

namespace App\Observers;

use App\Jobs\SendWorkOrderPush;
use App\Models\WorkOrder;

class WorkOrderObserver
{
    public function created(WorkOrder $workOrder): void
    {
        if ($workOrder->assigned_user_id) {
            $this->dispatch($workOrder, 'Nueva orden asignada', "{$workOrder->number} · {$workOrder->title}");
        }
    }

    public function updated(WorkOrder $workOrder): void
    {
        if (! $workOrder->assigned_user_id) {
            return;
        }
        if ($workOrder->wasChanged('assigned_user_id')) {
            $this->dispatch($workOrder, 'Nueva orden asignada', "{$workOrder->number} · {$workOrder->title}");

            return;
        }
        if ($workOrder->wasChanged(['status', 'scheduled_start', 'scheduled_end', 'scheduled_start_utc', 'scheduled_end_utc', 'title', 'instructions'])) {
            $this->dispatch($workOrder, 'Orden actualizada', "{$workOrder->number} · {$workOrder->title}");
        }
    }

    private function dispatch(WorkOrder $workOrder, string $title, string $body): void
    {
        if (! config('automation.approved.push', false)) {
            return;
        }

        SendWorkOrderPush::dispatch($workOrder->assigned_user_id, $workOrder->id, $title, $body)->afterCommit();
    }
}
