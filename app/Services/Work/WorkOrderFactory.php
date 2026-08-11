<?php

namespace App\Services\Work;

use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use Illuminate\Support\Facades\DB;

class WorkOrderFactory
{
    public function __construct(private ChecklistTemplate $checklists, private WorkOrderWorkflow $workflow) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?int $createdBy = null): WorkOrder
    {
        return DB::transaction(function () use ($data, $createdBy): WorkOrder {
            $installation = Installation::findOrFail($data['installation_id']);
            $template = filled($data['work_order_template_id'] ?? null)
                ? WorkOrderTemplate::where('is_active', true)->findOrFail($data['work_order_template_id'])
                : null;
            $kind = $data['kind'] ?? $template?->kind ?? 'installation';
            $order = WorkOrder::create([
                ...$data,
                'created_by' => $createdBy,
                'number' => 'TMP-'.str()->uuid(),
                'title' => $data['title'] ?? $template?->title ?? ucfirst($kind),
                'kind' => $kind,
                'status' => 'planned',
                'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? $template?->estimated_duration_minutes,
                'required_skills' => $data['required_skills'] ?? $template?->required_skills,
                'service_zone' => $data['service_zone'] ?? $installation->site_postal_code,
                'allowed_transitions_snapshot' => $template?->allowed_transitions ?: $this->workflow->defaultTransitions(),
            ]);
            $order->update(['number' => 'WO-'.now()->format('Y').'-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT)]);
            $items = $template?->checklist ?: $this->checklists->for($installation->service_type, $kind);
            $order->checklistItems()->createMany(collect($items)->map(function ($item, $position): array {
                return is_array($item)
                    ? [...$item, 'position' => $position + 1]
                    : ['label' => $item, 'is_required' => true, 'position' => $position + 1];
            })->all());
            $activity = filled($data['activity_id'] ?? null) ? CrmActivity::findOrFail($data['activity_id']) : new CrmActivity;
            $activity->fill([
                'installation_id' => $installation->id,
                'work_order_id' => $order->id,
                'person_id' => $installation->person_id,
                'assigned_user_id' => $order->assigned_user_id,
                'created_by' => $createdBy,
                'type' => in_array($kind, ['survey', 'maintenance', 'repair'], true) ? 'visit' : 'task',
                'title' => $order->title,
                'notes' => __('Linked to work order :number.', ['number' => $order->number]),
                'priority' => $kind === 'repair' ? 'high' : 'normal',
                'status' => 'pending',
                'starts_at' => $order->scheduled_start,
                'due_at' => $order->scheduled_end,
            ])->save();

            return $order->load('installation');
        });
    }
}
