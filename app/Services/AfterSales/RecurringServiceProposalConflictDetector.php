<?php

namespace App\Services\AfterSales;

use App\Models\MaintenancePlan;
use App\Models\ServiceCorrectivePlan;
use App\Models\ServiceCorrectiveProposal;

class RecurringServiceProposalConflictDetector
{
    /** @return array{target_type: string, target_id: int, target_version: string, target_snapshot: array<string, mixed>} */
    public function capture(ServiceCorrectivePlan $plan, string $type): array
    {
        $loadedPlan = $plan->relationLoaded('maintenancePlan') ? $plan->maintenancePlan : null;
        $maintenancePlan = $loadedPlan && array_key_exists('version', $loadedPlan->getAttributes())
            ? $loadedPlan->loadMissing(['template', 'preventiveMaterials', 'renewalOpportunity'])
            : MaintenancePlan::query()->with(['template', 'preventiveMaterials', 'renewalOpportunity'])->findOrFail($plan->maintenance_plan_id);

        $target = match ($type) {
            'work_order_template' => $this->workOrderTemplate($maintenancePlan),
            'preventive_stock' => $this->preventiveStock($maintenancePlan),
            'maintenance_scope' => $this->maintenanceScope($maintenancePlan),
            'renewal' => $this->renewal($maintenancePlan),
        };

        $snapshot = $target['snapshot'];
        $snapshot['fingerprint'] = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));

        return [
            'target_type' => $target['type'],
            'target_id' => $target['id'],
            'target_version' => $target['version'],
            'target_snapshot' => $snapshot,
        ];
    }

    /** @return array{has_conflict: bool, reason: string|null, prepared_version: string|null, current_version: string, current_snapshot: array<string, mixed>} */
    public function evaluate(ServiceCorrectiveProposal $proposal): array
    {
        $current = $this->capture($proposal->plan, $proposal->type);
        $prepared = $proposal->target_snapshot;
        $hasReferenceChange = $proposal->target_type !== $current['target_type']
            || (int) $proposal->target_id !== $current['target_id'];
        $hasContentChange = ! is_array($prepared)
            || ($prepared['fingerprint'] ?? null) !== ($current['target_snapshot']['fingerprint'] ?? null);

        return [
            'has_conflict' => $hasReferenceChange || $hasContentChange,
            'reason' => $hasReferenceChange
                ? 'target_reference_changed'
                : ($hasContentChange ? 'target_version_changed' : null),
            'prepared_version' => $proposal->target_version,
            'current_version' => $current['target_version'],
            'current_snapshot' => $current['target_snapshot'],
        ];
    }

    /** @return array{type: string, id: int, version: string, snapshot: array<string, mixed>} */
    private function workOrderTemplate(MaintenancePlan $plan): array
    {
        $template = $plan->template;
        $snapshot = $template?->only([
            'id', 'name', 'service_type', 'kind', 'title', 'estimated_duration_minutes',
            'required_skills', 'checklist', 'required_evidence', 'handover_documents',
            'allowed_transitions', 'is_active',
        ]) ?? ['id' => null];

        return [
            'type' => 'work_order_template', 'id' => (int) ($template?->id ?? 0),
            'version' => $template?->updated_at?->toISOString() ?? 'unassigned', 'snapshot' => $snapshot,
        ];
    }

    /** @return array{type: string, id: int, version: string, snapshot: array<string, mixed>} */
    private function preventiveStock(MaintenancePlan $plan): array
    {
        $materials = $plan->preventiveMaterials->sortBy('id')->map(fn ($material) => $material->only([
            'id', 'product_id', 'quantity_per_visit', 'expected_usage_percent', 'minimum_stock', 'notes',
        ]))->values()->all();

        return [
            'type' => 'maintenance_plan_material_set', 'id' => $plan->id,
            'version' => 'plan-'.$plan->version.'-'.substr(hash('sha256', json_encode($materials, JSON_THROW_ON_ERROR)), 0, 12),
            'snapshot' => ['maintenance_plan_version' => $plan->version, 'materials' => $materials],
        ];
    }

    /** @return array{type: string, id: int, version: string, snapshot: array<string, mixed>} */
    private function maintenanceScope(MaintenancePlan $plan): array
    {
        return [
            'type' => 'maintenance_plan', 'id' => $plan->id, 'version' => (string) $plan->version,
            'snapshot' => $plan->only([
                'id', 'version', 'work_order_template_id', 'service_level', 'coverage_type',
                'included_visits', 'response_minutes', 'resolution_minutes', 'interval_months',
                'lead_days', 'contract_ends_on', 'renewal_notice_days', 'scope', 'is_active',
            ]),
        ];
    }

    /** @return array{type: string, id: int, version: string, snapshot: array<string, mixed>} */
    private function renewal(MaintenancePlan $plan): array
    {
        $opportunity = $plan->renewalOpportunity;
        if ($opportunity) {
            return [
                'type' => 'opportunity', 'id' => $opportunity->id,
                'version' => $opportunity->updated_at->toISOString(),
                'snapshot' => $opportunity->only([
                    'id', 'code', 'commercial_kind', 'stage', 'probability', 'renewal_due_on',
                    'expected_close_on', 'owner_user_id',
                ]),
            ];
        }

        return [
            'type' => 'maintenance_plan', 'id' => $plan->id, 'version' => (string) $plan->version,
            'snapshot' => [
                'maintenance_plan_version' => $plan->version,
                'renewal_opportunity_id' => null,
                'contract_ends_on' => $plan->contract_ends_on?->toDateString(),
                'renewal_notice_days' => $plan->renewal_notice_days,
            ],
        ];
    }
}
