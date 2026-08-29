<?php

namespace App\Services\AfterSales;

use App\Models\MaintenancePlan;
use App\Models\Product;
use App\Models\ServiceCorrectiveProposal;
use App\Models\ServiceCorrectiveProposalApplication;
use App\Models\User;
use App\Models\WorkOrderTemplate;
use App\Services\Supply\PreventiveDemandForecast;
use App\Services\Supply\ReplenishmentTargetWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecurringServiceImprovementApplicator
{
    public function __construct(
        private RecurringServiceProposalConflictDetector $conflicts,
        private MaintenancePlanLifecycle $plans,
        private ReplenishmentTargetWorkflow $replenishment,
        private PreventiveDemandForecast $forecast,
    ) {}

    /** @return array<string, mixed> */
    public function preview(ServiceCorrectiveProposal $proposal): array
    {
        $proposal->loadMissing('plan.maintenancePlan.installation');
        $conflict = $this->conflicts->evaluate($proposal);
        $specific = match ($proposal->type) {
            'work_order_template' => $this->templatePreview($proposal),
            'maintenance_scope' => $this->scopePreview($proposal),
            'preventive_stock' => $this->stockPreview($proposal),
            'renewal' => $this->renewalPreview($proposal),
        };

        return [
            'can_apply' => ! $conflict['has_conflict'] && $specific['available'],
            'conflict' => $conflict,
            'current_version' => $conflict['current_version'],
            'proposed_change' => $proposal->payload['proposed_change'] ?? null,
            ...$specific,
        ];
    }

    /** @param array<string, mixed> $input */
    public function apply(ServiceCorrectiveProposal $proposal, array $input, string $note, User $actor): ServiceCorrectiveProposal
    {
        $attempt = ServiceCorrectiveProposalApplication::create([
            'service_corrective_proposal_id' => $proposal->id,
            'status' => 'started',
            'input' => [...$input, 'application_note' => trim($note)],
            'actor_id' => $actor->id,
        ]);

        try {
            $outcome = DB::transaction(function () use ($proposal, $input, $note, $actor, $attempt): array {
                $locked = ServiceCorrectiveProposal::query()->with('plan')->lockForUpdate()->findOrFail($proposal->id);
                if ($locked->status === 'applied') {
                    return ['proposal' => $locked, 'replayed' => true, 'result' => $locked->application_result];
                }
                if ($locked->status !== 'approved') {
                    throw ValidationException::withMessages(['application' => __('Only an approved improvement proposal can be applied.')]);
                }

                $conflict = $this->conflicts->evaluate($locked);
                if ($conflict['has_conflict']) {
                    throw ValidationException::withMessages(['application' => __('The destination changed after approval. Return and resubmit the proposal before applying it.')]);
                }

                $before = $this->conflicts->capture($locked->plan, $locked->type);
                $adapterResult = $this->execute($locked, $input, $note, $actor);
                $locked->plan->unsetRelation('maintenancePlan');
                $after = $this->conflicts->capture($locked->plan, $locked->type);
                $result = [
                    'adapter' => $locked->type,
                    'target_before_version' => $before['target_version'],
                    'target_after_version' => $after['target_version'],
                    ...$adapterResult,
                ];
                $locked->update([
                    'status' => 'applied', 'application_note' => trim($note),
                    'application_result' => $result, 'applied_by' => $actor->id, 'applied_at' => now(),
                ]);
                $locked->plan->events()->create([
                    'event' => 'proposal_applied', 'from_status' => 'approved', 'to_status' => 'applied',
                    'note' => trim($note), 'changes' => ['proposal_id' => $locked->id, 'application_id' => $attempt->id, 'result' => $result],
                    'actor_id' => $actor->id, 'created_at' => now(),
                ]);

                return ['proposal' => $locked->refresh(), 'replayed' => false, 'result' => $result, 'before' => $before, 'after' => $after];
            });

            $attempt->update([
                'status' => $outcome['replayed'] ? 'replayed' : 'applied',
                'target_before' => $outcome['before'] ?? null,
                'target_after' => $outcome['after'] ?? null,
                'result' => $outcome['result'],
            ]);

            return $outcome['proposal'];
        } catch (Throwable $exception) {
            $attempt->update([
                'status' => 'failed',
                'error_code' => class_basename($exception),
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function templatePreview(ServiceCorrectiveProposal $proposal): array
    {
        $template = WorkOrderTemplate::find($proposal->target_id);

        return [
            'available' => (bool) $template,
            'target' => $template ? ['id' => $template->id, 'name' => $template->name] : null,
            'current' => ['checklist_items' => count($template?->checklist ?? [])],
            'fields' => ['checklist_label'],
        ];
    }

    /** @return array<string, mixed> */
    private function scopePreview(ServiceCorrectiveProposal $proposal): array
    {
        $plan = $proposal->plan->maintenancePlan;

        return [
            'available' => true,
            'target' => ['id' => $plan->id, 'name' => $plan->name],
            'current' => ['scope' => $plan->scope, 'plan_version' => $plan->version],
            'fields' => ['scope_addition'],
        ];
    }

    /** @return array<string, mixed> */
    private function stockPreview(ServiceCorrectiveProposal $proposal): array
    {
        $plan = MaintenancePlan::with('preventiveMaterials.product')->findOrFail($proposal->plan->maintenance_plan_id);
        $rows = collect($this->forecast->forecast(today(), 180)['rows'])->keyBy('product_id');
        $products = $plan->preventiveMaterials->map(function ($material) use ($rows): array {
            $row = $rows->get($material->product_id);

            return [
                'id' => $material->product_id,
                'sku' => $material->product?->sku,
                'name' => $material->product?->name,
                'forecast_shortage' => (float) ($row['forecast_shortage'] ?? 0),
                'eligible' => (bool) ($material->product?->is_active && $material->product?->track_stock && ($row['forecast_shortage'] ?? 0) > 0),
            ];
        })->values();

        return [
            'available' => $products->contains('eligible', true),
            'target' => ['id' => $plan->id, 'name' => $plan->name],
            'current' => ['products' => $products->all()],
            'fields' => ['product_id', 'target_quantity'],
        ];
    }

    /** @return array<string, mixed> */
    private function renewalPreview(ServiceCorrectiveProposal $proposal): array
    {
        $plan = $proposal->plan->maintenancePlan;
        $available = (bool) ($plan->contract_ends_on && $plan->installation?->person_id);

        return [
            'available' => $available,
            'target' => ['id' => $plan->id, 'name' => $plan->name],
            'current' => [
                'contract_ends_on' => $plan->contract_ends_on?->toDateString(),
                'renewal_opportunity_id' => $plan->renewal_opportunity_id,
            ],
            'fields' => [],
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function execute(ServiceCorrectiveProposal $proposal, array $input, string $note, User $actor): array
    {
        return match ($proposal->type) {
            'work_order_template' => $this->applyTemplate($proposal, $input),
            'maintenance_scope' => $this->applyScope($proposal, $input, $note, $actor),
            'preventive_stock' => $this->applyStock($proposal, $input, $note, $actor),
            'renewal' => $this->applyRenewal($proposal, $actor),
        };
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function applyTemplate(ServiceCorrectiveProposal $proposal, array $input): array
    {
        $label = trim((string) ($input['checklist_label'] ?? ''));
        if ($label === '') {
            throw ValidationException::withMessages(['checklist_label' => __('Enter the checklist item that will be added to the template.')]);
        }
        $template = WorkOrderTemplate::query()->lockForUpdate()->findOrFail($proposal->target_id);
        $checklist = $template->checklist ?? [];
        if (count($checklist) >= 100) {
            throw ValidationException::withMessages(['checklist_label' => __('The work-order template already contains the maximum number of checklist items.')]);
        }
        $template->update(['checklist' => [...$checklist, ['label' => $label, 'is_required' => true]]]);

        return ['work_order_template_id' => $template->id, 'checklist_item' => $label];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function applyScope(ServiceCorrectiveProposal $proposal, array $input, string $note, User $actor): array
    {
        $addition = trim((string) ($input['scope_addition'] ?? ''));
        if ($addition === '') {
            throw ValidationException::withMessages(['scope_addition' => __('Enter the text that will be added to the maintenance scope.')]);
        }
        $plan = MaintenancePlan::findOrFail($proposal->target_id);
        $scope = trim(implode("\n\n", array_filter([$plan->scope, $addition])));
        if (mb_strlen($scope) > 10000) {
            throw ValidationException::withMessages(['scope_addition' => __('The resulting maintenance scope exceeds the allowed length.')]);
        }
        $updated = $this->plans->revise($plan, ['scope' => $scope], null, $note, $actor);

        return [
            'maintenance_plan_id' => $updated->id,
            'maintenance_plan_version' => $updated->version,
            'scope_addition' => $addition,
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function applyStock(ServiceCorrectiveProposal $proposal, array $input, string $note, User $actor): array
    {
        $productId = (int) ($input['product_id'] ?? 0);
        $quantity = (float) ($input['target_quantity'] ?? 0);
        $plan = MaintenancePlan::with('preventiveMaterials')->findOrFail($proposal->target_id);
        if (! $plan->preventiveMaterials->contains('product_id', $productId)) {
            throw ValidationException::withMessages(['product_id' => __('Select a preventive product belonging to this maintenance plan.')]);
        }
        $product = Product::findOrFail($productId);
        if (! $product->is_active || ! $product->track_stock) {
            throw ValidationException::withMessages(['product_id' => __('Only active stock products can receive replenishment objectives.')]);
        }
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['target_quantity' => __('Enter a positive replenishment objective.')]);
        }
        $target = $this->replenishment->approve($product, $quantity, $note, $actor);

        return ['replenishment_target_id' => $target->id, 'product_id' => $product->id, 'target_quantity' => (float) $target->target_quantity];
    }

    /** @return array<string, mixed> */
    private function applyRenewal(ServiceCorrectiveProposal $proposal, User $actor): array
    {
        $plan = MaintenancePlan::findOrFail($proposal->target_id);
        $opportunity = $this->plans->prepareRenewal($plan, $actor);

        return ['opportunity_id' => $opportunity->id, 'opportunity_code' => $opportunity->code];
    }
}
