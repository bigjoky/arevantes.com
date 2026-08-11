<?php

namespace App\Services\AfterSales;

use App\Models\MaintenancePlan;
use App\Models\ServiceTicket;
use App\Models\WorkOrder;
use App\Services\Work\WorkOrderFactory;
use Illuminate\Support\Facades\DB;

class ServiceWorkGenerator
{
    public function __construct(private WorkOrderFactory $orders, private SlaManager $sla, private ServiceTicketFactory $tickets) {}

    public function createForTicket(ServiceTicket $ticket, ?int $createdBy = null): WorkOrder
    {
        if ($ticket->workOrder) {
            return $ticket->workOrder;
        }

        return DB::transaction(function () use ($ticket, $createdBy): WorkOrder {
            $kind = match ($ticket->kind) {
                'maintenance', 'inspection' => 'maintenance',
                default => 'repair',
            };
            $order = $this->orders->create([
                'installation_id' => $ticket->installation_id,
                'work_order_template_id' => $ticket->maintenancePlan?->work_order_template_id,
                'assigned_user_id' => $ticket->assigned_user_id ?: $ticket->maintenancePlan?->assigned_user_id,
                'title' => $ticket->title,
                'kind' => $kind,
                'instructions' => $ticket->description,
                'estimated_duration_minutes' => $kind === 'repair' ? 120 : 90,
            ], $createdBy);
            $ticket->update(['work_order_id' => $order->id, 'status' => $order->assigned_user_id ? 'assigned' : 'open']);

            return $order;
        });
    }

    public function generateDueMaintenance(): int
    {
        $count = 0;
        MaintenancePlan::query()->with(['installation', 'tickets.workOrder'])->where('is_active', true)
            ->where('next_due_on', '<=', today()->addDays(30)->toDateString())->each(function (MaintenancePlan $plan) use (&$count): void {
                if ($plan->next_due_on->greaterThan(today()->addDays($plan->lead_days))) {
                    return;
                }
                $ticket = $plan->tickets()->whereNotIn('status', ['resolved', 'closed'])->latest()->first();
                if (! $ticket) {
                    $ticket = $this->tickets->create([
                        'installation_id' => $plan->installation_id,
                        'maintenance_plan_id' => $plan->id,
                        'assigned_user_id' => $plan->assigned_user_id,
                        'kind' => 'maintenance', 'priority' => 'normal', 'status' => 'open', 'title' => $plan->name, 'description' => $plan->scope,
                    ]);
                    $this->sla->apply($ticket);
                }
                if ($plan->generate_work_order && ! $ticket->work_order_id) {
                    $this->createForTicket($ticket);
                }
                $plan->update(['last_generated_at' => now()]);
                $count++;
            });

        return $count;
    }
}
