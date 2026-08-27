<?php

namespace App\Services\AfterSales;

use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanVersion;
use App\Models\WorkOrderTemplate;
use App\Services\Work\ChecklistTemplate;
use App\Services\Work\WorkOrderTemplateResolver;
use App\Services\Work\WorkOrderWorkflow;
use Illuminate\Validation\ValidationException;

class ServiceCycleTechnicalSnapshot
{
    public function __construct(
        private WorkOrderTemplateResolver $templates,
        private ChecklistTemplate $checklists,
        private WorkOrderWorkflow $workflow,
    ) {}

    /** @return array<string, mixed> */
    public function capture(MaintenancePlan $plan, int $version): array
    {
        $planVersion = MaintenancePlanVersion::query()
            ->where('maintenance_plan_id', $plan->id)
            ->where('version', $version)
            ->first();
        if (! $planVersion) {
            throw ValidationException::withMessages(['commitment' => __('The approved service plan version cannot be resolved.')]);
        }

        $planSnapshot = $planVersion->snapshot ?? [];
        $templateId = $planSnapshot['work_order_template_id'] ?? null;
        $installation = $plan->loadMissing('installation')->installation;
        $template = $templateId
            ? WorkOrderTemplate::find($templateId)
            : $this->templates->resolveForServiceType($installation->service_type, 'maintenance');
        $checklist = $template?->checklist ?: $this->checklists->for($installation->service_type, 'maintenance');

        return [
            'captured_at' => now()->toISOString(),
            'plan' => $planSnapshot,
            'template' => [
                'id' => $template?->id,
                'name' => $template?->name,
                'service_type' => $installation->service_type,
                'kind' => 'maintenance',
                'title' => $template?->title ?: ($planSnapshot['name'] ?? $plan->name),
                'estimated_duration_minutes' => $template?->estimated_duration_minutes ?: 90,
                'required_skills' => $template?->required_skills ?? [],
                'checklist' => $checklist,
                'required_evidence' => $template?->required_evidence ?? [],
                'handover_documents' => $template?->handover_documents ?? [],
                'allowed_transitions' => $template?->allowed_transitions ?: $this->workflow->defaultTransitions(),
            ],
        ];
    }
}
