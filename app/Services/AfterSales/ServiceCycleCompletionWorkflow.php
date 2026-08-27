<?php

namespace App\Services\AfterSales;

use App\Models\AssetEvent;
use App\Models\CustomerPortalEvent;
use App\Models\InstallationAsset;
use App\Models\MaintenancePlan;
use App\Models\ServiceCycleCommitment;
use App\Models\ServiceTicket;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceCycleCompletionWorkflow
{
    /** @return array{MaintenancePlan, ServiceCycleCommitment, ServiceTicket}|null */
    public function lockContext(int $workOrderId): ?array
    {
        $reference = ServiceCycleCommitment::query()
            ->where('work_order_id', $workOrderId)
            ->first(['id', 'maintenance_plan_id', 'service_ticket_id']);
        if (! $reference) {
            return null;
        }

        $plan = MaintenancePlan::query()->lockForUpdate()->findOrFail($reference->maintenance_plan_id);
        $commitment = ServiceCycleCommitment::query()->lockForUpdate()->findOrFail($reference->id);
        $ticket = ServiceTicket::query()->lockForUpdate()->findOrFail($commitment->service_ticket_id);

        return [$plan, $commitment, $ticket];
    }

    public function assertCanClose(?array $context, WorkOrder $order): void
    {
        if (! $context) {
            return;
        }
        [, $commitment] = $context;
        if ($commitment->completed_at) {
            return;
        }
        if ($commitment->status !== 'approved' || $commitment->schedule_status !== 'confirmed') {
            throw ValidationException::withMessages(['status' => __('Confirm the recurring service schedule before completing its work order.')]);
        }
        if ($order->scheduled_start?->isFuture()) {
            throw ValidationException::withMessages(['status' => __('A recurring service cannot be completed before its confirmed start time.')]);
        }
    }

    /** @param array{MaintenancePlan, ServiceCycleCommitment, ServiceTicket}|null $context */
    public function closeLocked(?array $context, WorkOrder $order, ?int $actorId): ?ServiceCycleCommitment
    {
        if (! $context) {
            return null;
        }
        [$plan, $commitment, $ticket] = $context;
        if ($commitment->completed_at) {
            return $commitment;
        }

        $completedAt = $order->completed_at ?: now();
        $nextDueBefore = $plan->next_due_on?->copy();
        $nextDueAfter = $nextDueBefore?->copy();
        while ($nextDueAfter && $nextDueAfter->lessThanOrEqualTo($commitment->period_ends_on)) {
            $nextDueAfter = $nextDueAfter->addMonthsNoOverflow(max(1, $plan->interval_months));
        }

        $checklist = $order->checklistItems()->orderBy('position')->get();
        $attachments = $order->attachments()->orderBy('id')->get();
        $reservations = $order->stockReservations()->orderBy('id')->get();
        $timeEntries = $order->timeEntries()->orderBy('id')->get();
        $materials = DB::table('stock_movement_lines')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->where('stock_movements.work_order_id', $order->id)
            ->where('stock_movements.type', 'issue')
            ->groupBy('stock_movement_lines.product_id')
            ->selectRaw('stock_movement_lines.product_id, COALESCE(SUM(-stock_movement_lines.quantity), 0) quantity, COALESCE(SUM(-stock_movement_lines.quantity * COALESCE(stock_movement_lines.unit_cost, 0)), 0) cost')
            ->orderBy('stock_movement_lines.product_id')
            ->get();
        $laborCost = $timeEntries->sum(fn ($entry): float => (($entry->duration_minutes ?? 0) / 60) * (float) $entry->hourly_cost);
        $travelKm = $timeEntries->sum(fn ($entry): float => (float) $entry->travel_km);
        $travelCost = $timeEntries->sum(fn ($entry): float => (float) $entry->travel_km * (float) $entry->mileage_rate);
        $assetIds = collect($commitment->technical_snapshot['plan']['covered_asset_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values();

        $snapshot = [
            'captured_at' => now()->toISOString(),
            'work_order' => [
                'id' => $order->id,
                'number' => $order->number,
                'scheduled_start' => $order->scheduled_start?->toISOString(),
                'scheduled_end' => $order->scheduled_end?->toISOString(),
                'completed_at' => $completedAt->toISOString(),
                'completion_notes' => $order->completion_notes,
                'signed_by_name' => $order->signed_by_name,
                'signed_at' => $order->signed_at?->toISOString(),
            ],
            'checklist' => [
                'required' => $checklist->where('is_required', true)->count(),
                'completed' => $checklist->whereNotNull('completed_at')->count(),
                'items' => $checklist->map(fn ($item) => [
                    'id' => $item->id, 'position' => $item->position, 'label' => $item->label,
                    'is_required' => $item->is_required, 'completed_by' => $item->completed_by,
                    'completed_at' => $item->completed_at?->toISOString(), 'notes' => $item->notes,
                ])->values()->all(),
            ],
            'evidence' => [
                'total' => $attachments->count(),
                'photos' => $attachments->where('kind', 'photo')->count(),
                'documents' => $attachments->where('kind', 'document')->count(),
                'published_documents' => $attachments->where('kind', 'document')->where('publication_status', 'published')->count(),
                'pending_documents' => $attachments->where('kind', 'document')->whereIn('publication_status', ['internal', 'pending_review'])->count(),
                'items' => $attachments->map(fn ($attachment) => [
                    'id' => $attachment->id, 'kind' => $attachment->kind, 'evidence_key' => $attachment->evidence_key,
                    'document_version' => $attachment->document_version, 'publication_status' => $attachment->publication_status,
                    'customer_visible' => $attachment->customer_visible, 'size' => $attachment->size,
                ])->values()->all(),
            ],
            'materials' => [
                'reserved_items' => $reservations->count(),
                'reserved_quantity' => round((float) $reservations->sum(fn ($item) => (float) $item->quantity), 3),
                'consumed_quantity' => round((float) $reservations->sum(fn ($item) => (float) $item->consumed_quantity), 3),
                'actual_cost' => round((float) $materials->sum('cost'), 2),
                'items' => $materials->map(fn ($item) => ['product_id' => (int) $item->product_id, 'quantity' => round((float) $item->quantity, 3), 'cost' => round((float) $item->cost, 2)])->values()->all(),
            ],
            'time' => [
                'entries' => $timeEntries->count(),
                'minutes' => (int) $timeEntries->sum('duration_minutes'),
                'travel_km' => round((float) $travelKm, 2),
                'labor_cost' => round((float) $laborCost, 2),
                'travel_cost' => round((float) $travelCost, 2),
            ],
            'assets' => ['ids' => $assetIds->all(), 'serviced' => $assetIds->count()],
            'continuity' => [
                'next_due_on_before' => $nextDueBefore?->toDateString(),
                'next_due_on_after' => $nextDueAfter?->toDateString(),
            ],
        ];

        $reservations->where('status', 'active')->each->update(['status' => 'released']);
        $ticket->update([
            'status' => 'resolved',
            'resolution' => $order->completion_notes,
            'first_responded_at' => $ticket->first_responded_at ?: $completedAt,
            'resolved_at' => $ticket->resolved_at ?: $completedAt,
        ]);
        $plan->update([
            'last_completed_on' => $completedAt->toDateString(),
            'next_due_on' => $nextDueAfter?->toDateString(),
        ]);
        $commitment->update([
            'status' => 'completed',
            'completion_snapshot' => $snapshot,
            'completed_by' => $actorId,
            'completed_at' => $completedAt,
        ]);

        InstallationAsset::query()->whereKey($assetIds)->orderBy('id')->get()->each(function (InstallationAsset $asset) use ($commitment, $order, $ticket, $actorId, $completedAt): void {
            $asset->increment('service_count', 1, ['last_serviced_at' => $completedAt]);
            AssetEvent::firstOrCreate([
                'installation_asset_id' => $asset->id,
                'service_cycle_commitment_id' => $commitment->id,
            ], [
                'work_order_id' => $order->id,
                'service_ticket_id' => $ticket->id,
                'recorded_by' => $actorId,
                'type' => 'maintenance',
                'summary' => __('Recurring preventive service completed.'),
                'details' => $order->completion_notes,
                'occurred_at' => $completedAt,
            ]);
        });

        $customer = $plan->loadMissing('installation.customer')->installation?->customer;
        if ($customer) {
            CustomerPortalEvent::record($customer, 'service_cycle_completed', null, null, $commitment, [
                'plan' => $plan->name,
                'work_order' => $order->number,
                'next_due_on' => $nextDueAfter?->toDateString(),
            ]);
        }

        return $commitment->refresh();
    }
}
