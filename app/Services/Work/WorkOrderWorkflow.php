<?php

namespace App\Services\Work;

use App\Models\WorkOrder;
use App\Services\Operations\FinancialClearanceGuard;
use Illuminate\Validation\ValidationException;

class WorkOrderWorkflow
{
    public function __construct(private FinancialClearanceGuard $financialClearance) {}

    public const DEFAULT_TRANSITIONS = [
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
        $order->loadMissing('installation');
        if ($order->installation->dossier_closed_at && in_array($order->kind, ['installation', 'commissioning'], true)) {
            throw ValidationException::withMessages(['status' => 'Reabre el expediente antes de modificar la ejecución.']);
        }
        if (in_array($to, ['in_progress', 'completed'], true)) {
            app(TechnicalExecutionGuard::class)->assertAllows($order->loadMissing('installation')->installation, $order->kind);
            $this->financialClearance->assertAllows($order->loadMissing('installation')->installation, $order->kind);
        }
        $transitions = $order->allowed_transitions_snapshot ?: self::DEFAULT_TRANSITIONS;
        if (! in_array($to, $transitions[$order->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('Transition from :from to :to is not allowed.', ['from' => $order->status, 'to' => $to])]);
        }
    }

    /** @return array<string, array<int, string>> */
    public function defaultTransitions(): array
    {
        return self::DEFAULT_TRANSITIONS;
    }
}
