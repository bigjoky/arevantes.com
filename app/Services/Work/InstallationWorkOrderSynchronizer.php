<?php

namespace App\Services\Work;

use App\Models\WorkOrder;

class InstallationWorkOrderSynchronizer
{
    public function sync(WorkOrder $order): void
    {
        if (! in_array($order->kind, ['survey', 'installation', 'commissioning'], true)) {
            return;
        }
        $order->loadMissing('installation');
        if ($order->installation->status === 'cancelled') {
            return;
        }
        $execution = WorkOrder::where('installation_id', $order->installation_id)
            ->whereIn('kind', ['installation', 'commissioning'])->where('status', '!=', 'cancelled')
            ->get(['status', 'scheduled_start', 'completed_at']);
        $updates = [];
        if ($execution->isNotEmpty()) {
            $status = match (true) {
                $execution->contains('status', 'in_progress') => 'in_progress',
                $execution->every(fn ($item) => $item->status === 'completed') => 'completed',
                $execution->where('status', '!=', 'completed')->contains(fn ($item) => $item->scheduled_start !== null) => 'scheduled',
                default => 'approved',
            };
            $updates = ['status' => $status, 'completed_date' => $status === 'completed' ? $execution->max('completed_at')?->toDateString() : null];
        } elseif ($order->kind === 'survey') {
            $updates = ['status' => $order->status === 'completed' ? 'approved' : ($order->scheduled_start ? 'survey_booked' : 'approved')];
        } else {
            $updates = ['status' => 'approved', 'completed_date' => null];
        }
        // Administrative closure is intentionally unaffected by physical execution status.
        $order->installation->update($updates);
    }
}
