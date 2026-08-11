<?php

namespace App\Services\Work;

use App\Models\WorkOrder;

class InstallationWorkOrderSynchronizer
{
    public function sync(WorkOrder $order): void
    {
        $order->loadMissing('installation');
        $openExecutionOrders = WorkOrder::query()
            ->where('installation_id', $order->installation_id)
            ->where('id', '!=', $order->id)
            ->whereIn('kind', ['installation', 'commissioning'])
            ->whereNotIn('status', ['completed', 'cancelled']);
        $completedExecutionStatus = (clone $openExecutionOrders)->exists()
            ? ((clone $openExecutionOrders)->where('status', 'in_progress')->exists() ? 'in_progress' : 'scheduled')
            : 'completed';
        $status = match (true) {
            $order->kind === 'survey' && $order->status === 'completed' => 'approved',
            $order->kind === 'survey' => 'survey_booked',
            in_array($order->kind, ['installation', 'commissioning'], true) && $order->status === 'completed' => $completedExecutionStatus,
            in_array($order->kind, ['installation', 'commissioning'], true) && $order->status === 'in_progress' => 'in_progress',
            in_array($order->kind, ['installation', 'commissioning'], true) && $order->scheduled_start => 'scheduled',
            default => null,
        };

        if ($status && $order->installation->status !== 'cancelled') {
            $order->installation->update(['status' => $status]);
        }
    }
}
