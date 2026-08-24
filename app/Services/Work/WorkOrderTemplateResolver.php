<?php

namespace App\Services\Work;

use App\Models\Installation;
use App\Models\WorkOrderTemplate;
use Illuminate\Validation\ValidationException;

class WorkOrderTemplateResolver
{
    public function resolve(Installation $installation, string $kind, ?int $templateId = null): ?WorkOrderTemplate
    {
        $template = $templateId
            ? WorkOrderTemplate::where('is_active', true)->findOrFail($templateId)
            : $this->resolveForServiceType($installation->service_type, $kind);

        if ($template?->service_type && $template->service_type !== $installation->service_type) {
            throw ValidationException::withMessages([
                'work_order_template_id' => __('The selected template does not belong to this installation business line.'),
            ]);
        }

        if ($template && $template->kind !== $kind) {
            throw ValidationException::withMessages([
                'work_order_template_id' => __('The selected template does not match this work type.'),
            ]);
        }

        return $template;
    }

    public function resolveForServiceType(string $serviceType, string $kind): ?WorkOrderTemplate
    {
        return WorkOrderTemplate::query()
            ->where('is_active', true)
            ->where('service_type', $serviceType)
            ->where('kind', $kind)
            ->orderByDesc('is_system')
            ->orderBy('id')
            ->first();
    }

    /** @param array<string, mixed> $data */
    public function applyDefaults(array $data): array
    {
        $installation = Installation::findOrFail($data['installation_id']);
        $explicitTemplate = filled($data['work_order_template_id'] ?? null)
            ? WorkOrderTemplate::where('is_active', true)->findOrFail($data['work_order_template_id'])
            : null;
        $kind = $data['kind'] ?? $explicitTemplate?->kind ?? 'installation';
        $template = $this->resolve(
            $installation,
            $kind,
            filled($data['work_order_template_id'] ?? null) ? (int) $data['work_order_template_id'] : null,
        );

        if (! $template) {
            return $data;
        }

        return [
            ...$data,
            'kind' => $kind,
            'work_order_template_id' => $template->id,
            'title' => $data['title'] ?? $template->title,
            'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? $template->estimated_duration_minutes,
            'required_skills' => $data['required_skills'] ?? $template->required_skills,
            'required_evidence' => $template->required_evidence ?? [],
            'handover_documents' => $template->handover_documents ?? [],
            'allowed_transitions_snapshot' => $template->allowed_transitions,
        ];
    }
}
