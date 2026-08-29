<?php

namespace App\Services\AfterSales;

use App\Models\MaintenancePlan;
use App\Models\Opportunity;
use App\Models\ReplenishmentTarget;
use App\Models\ServiceCorrectiveProposal;
use App\Models\ServiceCycleCommitment;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use Illuminate\Support\Collection;

class RecurringServiceImprovementMonitor
{
    /** @return array<string, mixed> */
    public function summarize(ServiceCorrectiveProposal $proposal): array
    {
        if ($proposal->superseded_at) {
            return $this->summary($proposal, 'superseded', true, collect(), collect());
        }

        return match ($proposal->type) {
            'work_order_template' => $this->template($proposal),
            'maintenance_scope' => $this->scope($proposal),
            'preventive_stock' => $this->stock($proposal),
            'renewal' => $this->renewal($proposal),
        };
    }

    /** @return array<string, mixed> */
    private function template(ServiceCorrectiveProposal $proposal): array
    {
        $result = $proposal->application_result ?? [];
        $template = WorkOrderTemplate::find($result['work_order_template_id'] ?? null);
        $label = trim((string) ($result['checklist_item'] ?? ''));
        $implemented = $template && $label !== '' && collect($template->checklist ?? [])->contains(
            fn (array|string $item): bool => trim((string) (is_array($item) ? ($item['label'] ?? '') : $item)) === $label,
        );

        $cycles = $this->cycles($proposal)->filter(function (ServiceCycleCommitment $cycle) use ($template, $label): bool {
            $snapshot = $cycle->technical_snapshot ?? [];
            $snapshotTemplate = $snapshot['template'] ?? [];

            return (int) ($snapshotTemplate['id'] ?? 0) === (int) $template?->id
                && collect($snapshotTemplate['checklist'] ?? [])->contains(
                    fn (array|string $item): bool => trim((string) (is_array($item) ? ($item['label'] ?? '') : $item)) === $label,
                );
        });

        $installationId = $proposal->plan->maintenancePlan->installation_id;
        $orders = $template ? WorkOrder::query()
            ->with('checklistItems:id,work_order_id,label,is_required,completed_at')
            ->where('work_order_template_id', $template->id)
            ->where('installation_id', $installationId)
            ->where('created_at', '>=', $proposal->applied_at)
            ->latest()->limit(100)->get()
            ->filter(fn (WorkOrder $order): bool => $order->checklistItems->contains('label', $label)) : collect();

        return $this->summary($proposal, $implemented ? null : 'implementation_failed', $implemented, $cycles, $orders);
    }

    /** @return array<string, mixed> */
    private function scope(ServiceCorrectiveProposal $proposal): array
    {
        $result = $proposal->application_result ?? [];
        $plan = MaintenancePlan::find($result['maintenance_plan_id'] ?? null);
        $addition = trim((string) ($result['scope_addition'] ?? ''));
        $implemented = $plan && (int) $plan->version >= (int) ($result['maintenance_plan_version'] ?? PHP_INT_MAX)
            && ($addition === '' || str_contains((string) $plan->scope, $addition));
        $cycles = $this->cycles($proposal)->filter(
            fn (ServiceCycleCommitment $cycle): bool => $cycle->maintenance_plan_version >= (int) ($result['maintenance_plan_version'] ?? PHP_INT_MAX),
        );

        return $this->summary($proposal, $implemented ? null : 'implementation_failed', $implemented, $cycles, collect());
    }

    /** @return array<string, mixed> */
    private function stock(ServiceCorrectiveProposal $proposal): array
    {
        $target = ReplenishmentTarget::with('product:id,sku,name')->find($proposal->application_result['replenishment_target_id'] ?? null);
        if (! $target) {
            return $this->summary($proposal, 'implementation_failed', false, collect(), collect());
        }

        $consumer = collect([[
            'type' => 'replenishment_target', 'id' => $target->id,
            'label' => trim(($target->product?->sku ? $target->product->sku.' · ' : '').($target->product?->name ?? __('Replenishment objective'))),
            'status' => $target->status, 'used_at' => $target->approved_at?->toISOString(),
            'href' => '/purchases/control-tower',
        ]]);

        return $this->summary($proposal, $target->status === 'approved' ? 'monitoring' : 'implementation_failed', $target->status === 'approved', collect(), $consumer, true);
    }

    /** @return array<string, mixed> */
    private function renewal(ServiceCorrectiveProposal $proposal): array
    {
        $opportunity = Opportunity::withTrashed()->find($proposal->application_result['opportunity_id'] ?? null);
        if (! $opportunity) {
            return $this->summary($proposal, 'implementation_failed', false, collect(), collect());
        }

        $consumer = collect([[
            'type' => 'opportunity', 'id' => $opportunity->id,
            'label' => $opportunity->code.' · '.$opportunity->title,
            'status' => $opportunity->trashed() ? 'deleted' : $opportunity->stage,
            'used_at' => $opportunity->created_at?->toISOString(),
            'href' => '/opportunities/'.$opportunity->id,
        ]]);
        $state = $opportunity->trashed() ? 'implementation_failed' : match ($opportunity->stage) {
            'won' => 'effective', 'lost' => 'ineffective', default => 'monitoring',
        };

        return $this->summary($proposal, $state, ! $opportunity->trashed(), collect(), $consumer, true);
    }

    /** @return Collection<int, ServiceCycleCommitment> */
    private function cycles(ServiceCorrectiveProposal $proposal): Collection
    {
        return ServiceCycleCommitment::query()
            ->with(['workOrder:id,number,status,completed_at', 'deviations:id,service_cycle_commitment_id,category,status'])
            ->where('maintenance_plan_id', $proposal->plan->maintenance_plan_id)
            ->where('created_at', '>=', $proposal->applied_at)
            ->oldest('created_at')
            ->limit(100)
            ->get();
    }

    /** @param Collection<int, ServiceCycleCommitment> $cycles @param Collection<int, mixed> $otherConsumers @return array<string, mixed> */
    private function summary(ServiceCorrectiveProposal $proposal, ?string $forcedState, bool $implemented, Collection $cycles, Collection $otherConsumers, bool $otherConsumersArePresented = false): array
    {
        $completed = $cycles->filter(fn (ServiceCycleCommitment $cycle): bool => (bool) $cycle->completed_at);
        $categories = collect($proposal->plan->baseline_snapshot['categories'] ?? [])->filter();
        $confirmedDeviationCount = $completed->sum(fn (ServiceCycleCommitment $cycle): int => $cycle->deviations
            ->whereIn('status', ['confirmed', 'resolved'])
            ->when($categories->isNotEmpty(), fn (Collection $rows) => $rows->whereIn('category', $categories))
            ->count());
        $opportunities = $completed->count() * max(1, $categories->count());
        $observedRate = $opportunities > 0 ? round($confirmedDeviationCount / $opportunities, 4) : null;
        $baselineRate = (float) ($proposal->plan->baseline_snapshot['deviation_rate'] ?? 0);
        $requiredCycles = max(1, (int) $proposal->plan->verification_cycles);

        $state = $forcedState;
        if ($state === null) {
            if ($cycles->isEmpty() && $otherConsumers->isEmpty()) {
                $state = 'not_used';
            } elseif ($completed->count() < $requiredCycles) {
                $state = 'monitoring';
            } else {
                $state = $observedRate !== null && $observedRate < $baselineRate ? 'effective' : 'ineffective';
            }
        }

        $cycleConsumers = $cycles->map(fn (ServiceCycleCommitment $cycle): array => [
            'type' => 'service_cycle', 'id' => $cycle->id,
            'label' => __('Service cycle :date', ['date' => $cycle->period_starts_on->format('d/m/Y')]),
            'status' => $cycle->completed_at ? 'completed' : ($cycle->work_order_id ? 'materialized' : $cycle->status),
            'used_at' => ($cycle->approved_at ?? $cycle->created_at)?->toISOString(),
            'href' => '/after-sales',
        ]);
        $presentedOther = $otherConsumersArePresented ? $otherConsumers : $otherConsumers->map(fn (WorkOrder $order): array => [
            'type' => 'work_order', 'id' => $order->id, 'label' => $order->number,
            'status' => $order->status, 'used_at' => $order->created_at?->toISOString(),
            'href' => '/work-orders?search='.urlencode($order->number),
        ]);
        $consumers = $cycleConsumers->concat($presentedOther)->sortBy('used_at')->values();

        return [
            'state' => $state,
            'implemented' => $implemented,
            'adoption_count' => $consumers->count(),
            'completed_count' => $completed->count(),
            'required_cycles' => $requiredCycles,
            'first_used_at' => $consumers->first()['used_at'] ?? null,
            'baseline_deviation_rate' => $baselineRate,
            'observed_deviation_rate' => $observedRate,
            'confirmed_deviation_count' => $confirmedDeviationCount,
            'consumers' => $consumers->take(5)->all(),
        ];
    }
}
