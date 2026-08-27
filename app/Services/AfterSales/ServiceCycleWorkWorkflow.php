<?php

namespace App\Services\AfterSales;

use App\Models\ServiceCycleCommitment;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Inventory\WorkOrderKitAllocator;
use App\Services\Work\TechnicianSchedule;
use App\Services\Work\WorkOrderFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceCycleWorkWorkflow
{
    public function __construct(
        private ServiceTicketFactory $tickets,
        private WorkOrderFactory $orders,
        private SlaManager $sla,
        private TechnicianSchedule $schedule,
        private ServiceCycleTechnicalSnapshot $snapshots,
        private WorkOrderKitAllocator $kits,
    ) {}

    /** @return array{work_order: WorkOrder, created: bool} */
    public function materialize(ServiceCycleCommitment $commitment, string $scheduledStart, string $reason, User $actor): array
    {
        return DB::transaction(function () use ($commitment, $scheduledStart, $reason, $actor): array {
            $locked = ServiceCycleCommitment::query()->with('maintenancePlan.installation')->lockForUpdate()->findOrFail($commitment->id);
            if ($locked->work_order_id && $locked->service_ticket_id) {
                return ['work_order' => WorkOrder::findOrFail($locked->work_order_id), 'created' => false];
            }
            if ($locked->status !== 'approved') {
                throw ValidationException::withMessages(['commitment' => __('Only approved service cycles can be materialized.')]);
            }

            $snapshot = $locked->technical_snapshot ?: $this->snapshots->capture($locked->maintenancePlan, $locked->maintenance_plan_version);
            $plan = $snapshot['plan'] ?? [];
            $template = $snapshot['template'] ?? [];
            $start = Carbon::parse($scheduledStart);
            if ($start->toDateString() < $locked->period_starts_on->toDateString() || $start->toDateString() > $locked->period_ends_on->toDateString()) {
                throw ValidationException::withMessages(['scheduled_start' => __('The visit must fall within the approved service cycle.')]);
            }
            $minutes = max(1, (int) ($template['estimated_duration_minutes'] ?? $locked->planned_minutes));
            $end = $start->copy()->addMinutes($minutes);
            $assignedUserId = (int) ($locked->capacity_snapshot['assigned_user_id'] ?? $plan['assigned_user_id'] ?? 0) ?: null;
            if (! $assignedUserId) {
                throw ValidationException::withMessages(['assigned_user_id' => __('The approved capacity snapshot has no responsible technician.')]);
            }
            $this->schedule->lockTechnicians([$assignedUserId]);
            $this->schedule->assertAvailable($assignedUserId, $start, $end, null, $template['required_skills'] ?? [], $locked->maintenancePlan->installation->site_postal_code);

            $ticket = $this->tickets->create([
                'installation_id' => $locked->maintenancePlan->installation_id,
                'maintenance_plan_id' => $locked->maintenance_plan_id,
                'maintenance_plan_version' => $locked->maintenance_plan_version,
                'assigned_user_id' => $assignedUserId,
                'kind' => 'maintenance',
                'priority' => 'normal',
                'status' => 'open',
                'title' => $plan['name'] ?? $locked->maintenancePlan->name,
                'description' => $plan['scope'] ?? null,
            ], $actor->id);
            $this->sla->apply($ticket);

            $order = $this->orders->create([
                'installation_id' => $locked->maintenancePlan->installation_id,
                'work_order_template_id' => null,
                'frozen_technical_contract' => true,
                'assigned_user_id' => $assignedUserId,
                'title' => $template['title'] ?? $ticket->title,
                'kind' => 'maintenance',
                'instructions' => $plan['scope'] ?? $ticket->description,
                'estimated_duration_minutes' => $minutes,
                'required_skills' => $template['required_skills'] ?? [],
                'required_evidence' => $template['required_evidence'] ?? [],
                'handover_documents' => $template['handover_documents'] ?? [],
                'allowed_transitions_snapshot' => $template['allowed_transitions'] ?? [],
                'checklist' => $template['checklist'] ?? [],
                'scheduled_start' => $start,
                'scheduled_end' => $end,
            ], $actor->id);
            $ticket->update(['work_order_id' => $order->id, 'status' => 'assigned']);
            $this->kits->reserveProducts($order, $plan['preventive_materials'] ?? []);

            $locked->update([
                'technical_snapshot' => $snapshot,
                'service_ticket_id' => $ticket->id,
                'work_order_id' => $order->id,
                'schedule_status' => 'pending_confirmation',
                'materialized_by' => $actor->id,
                'materialized_at' => now(),
            ]);
            $locked->scheduleRevisions()->create([
                'work_order_id' => $order->id,
                'version' => 1,
                'status' => 'pending_confirmation',
                'scheduled_start' => $start,
                'scheduled_end' => $end,
                'assigned_user_id' => $assignedUserId,
                'reason' => trim($reason),
                'created_by' => $actor->id,
            ]);

            return ['work_order' => $order, 'created' => true];
        });
    }
}
