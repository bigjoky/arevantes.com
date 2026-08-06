<?php

namespace App\Services\Work;

use App\Models\WorkOrder;
use App\Services\Inventory\ProjectMaterialPlanner;

class WorkOrderCompletionGuard
{
    public function __construct(private ProjectMaterialPlanner $materials) {}

    public function assertCanComplete(WorkOrder $order, ?string $completionNotes): void
    {
        $errors = [];
        if ($order->timeEntries()->whereNull('ended_at')->exists()) {
            $errors['status'][] = __('Stop the active timer before completing the work order.');
        }
        if ($order->checklistItems()->where('is_required', true)->whereNull('completed_at')->exists()) {
            $errors['status'][] = __('Complete all required checklist items first.');
        }
        if (! $order->signed_at) {
            $errors['status'][] = __('Capture the customer signature before completing the work order.');
        }
        if (! $order->attachments()->where('kind', 'photo')->exists()) {
            $errors['status'][] = __('Upload at least one completion photo.');
        }
        if (blank($completionNotes)) {
            $errors['completion_notes'][] = __('Completion observations are required.');
        }
        if (in_array($order->kind, ['installation', 'commissioning', 'repair'], true) && $order->installation->materialRequirements()->exists()) {
            $remaining = collect($this->materials->summary($order->installation)['rows'])->sum('remaining_quantity');
            if ($remaining > 0) {
                $errors['status'][] = __('Record consumption of all planned materials before closing.');
            }
        }
        abort_if($errors !== [], 422, collect($errors)->flatten()->join(' '));
    }
}
