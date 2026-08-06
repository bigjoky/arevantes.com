<?php

namespace App\Services\Work;

use App\Models\WorkOrder;
use Illuminate\Validation\ValidationException;

class WorkOrderWorkflow
{
    private const DEFAULT_TRANSITIONS = [
        'planned' => ['in_progress', 'blocked', 'completed', 'cancelled'],
        'in_progress' => ['blocked', 'completed', 'cancelled'],
        'blocked' => ['planned', 'in_progress', 'cancelled'],
        'completed' => [],
        'cancelled' => ['planned'],
    ];

    public function assertTransition(WorkOrder $order, string $to): void
    {
        if ($to === $order->status) {
            return;
        }
        $transitions = $order->template?->allowed_transitions ?: self::DEFAULT_TRANSITIONS;
        if (! in_array($to, $transitions[$order->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('Transition from :from to :to is not allowed.', ['from' => $order->status, 'to' => $to])]);
        }
    }
}
