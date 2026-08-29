<?php

namespace App\Http\Controllers;

use App\Models\ServiceCorrectivePlan;
use App\Models\ServiceCycleCommitment;
use App\Models\ServiceCycleDeviation;
use App\Models\User;
use App\Services\AfterSales\RecurringServiceEffectivenessEvaluator;
use App\Services\AfterSales\RecurringServiceQualityDetector;
use App\Services\AfterSales\RecurringServiceQualityWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecurringServiceQualityController extends Controller
{
    public function index(Request $request, RecurringServiceEffectivenessEvaluator $effectiveness): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'confirmed', 'dismissed', 'resolved'])],
            'category' => ['nullable', Rule::in(['duration', 'travel', 'materials', 'recurrence', 'evidence', 'documents'])],
            'business_line' => ['nullable', 'string', 'max:40'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $canViewFinancials = $request->user()->can('view-finance');
        $query = ServiceCycleDeviation::query()
            ->with([
                'assignee:id,name', 'events.actor:id,name', 'assets:id,asset_tag,name',
                'correctivePlans:id,code,status',
                'commitment:id,maintenance_plan_id,service_ticket_id,work_order_id,period_starts_on,period_ends_on,completed_at',
                'commitment.maintenancePlan:id,installation_id,name,assigned_user_id',
                'commitment.maintenancePlan.installation:id,project_reference,site_name,service_type',
                'commitment.serviceTicket:id,number,reopened_count', 'commitment.workOrder:id,number',
            ])
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($filters['category'] ?? null, fn ($builder, $category) => $builder->where('category', $category))
            ->when($filters['business_line'] ?? null, fn ($builder, $line) => $builder->where('business_line', $line))
            ->when($filters['assigned_to'] ?? null, fn ($builder, $userId) => $builder->where('assigned_to', $userId))
            ->orderByRaw("case severity when 'critical' then 0 when 'high' then 1 else 2 end")
            ->orderByRaw("case when status in ('open','confirmed') then 0 else 1 end")
            ->orderBy('review_due_on');

        $deviations = $query->get()->map(function (ServiceCycleDeviation $deviation) use ($canViewFinancials): array {
            $financial = $deviation->category === 'materials';

            return [
                'id' => $deviation->id,
                'reference' => 'DEV-'.str_pad((string) $deviation->id, 6, '0', STR_PAD_LEFT),
                'business_line' => $deviation->business_line,
                'category' => $deviation->category,
                'severity' => $deviation->severity,
                'status' => $deviation->status,
                'planned_value' => ! $financial || $canViewFinancials ? $deviation->planned_value : null,
                'actual_value' => ! $financial || $canViewFinancials ? $deviation->actual_value : null,
                'variance_percent' => $deviation->variance_percent,
                'tolerance_percent' => $deviation->tolerance_percent,
                'unit' => ! $financial || $canViewFinancials ? $deviation->unit : null,
                'summary' => __($deviation->summary),
                'detection_context' => $deviation->detection_context,
                'review_due_on' => $deviation->review_due_on->toDateString(),
                'detected_at' => $deviation->detected_at->toISOString(),
                'confirmed_at' => $deviation->confirmed_at?->toISOString(),
                'resolved_at' => $deviation->resolved_at?->toISOString(),
                'resolution_note' => $deviation->resolution_note,
                'assignee' => $deviation->assignee,
                'assets' => $deviation->assets,
                'events' => $deviation->events->map(fn ($event) => [
                    'id' => $event->id, 'event' => $event->event, 'from_status' => $event->from_status,
                    'to_status' => $event->to_status, 'note' => $event->note,
                    'created_at' => $event->created_at->toISOString(), 'actor' => $event->actor,
                ])->values(),
                'corrective_plans' => $deviation->correctivePlans->map->only(['id', 'code', 'status'])->values(),
                'cycle' => [
                    'id' => $deviation->commitment->id,
                    'maintenance_plan_id' => $deviation->commitment->maintenance_plan_id,
                    'period_starts_on' => $deviation->commitment->period_starts_on->toDateString(),
                    'period_ends_on' => $deviation->commitment->period_ends_on->toDateString(),
                    'completed_at' => $deviation->commitment->completed_at?->toISOString(),
                    'plan' => $deviation->commitment->maintenancePlan->name,
                    'installation' => $deviation->commitment->maintenancePlan->installation,
                    'ticket' => $deviation->commitment->serviceTicket,
                    'work_order' => $deviation->commitment->workOrder,
                ],
            ];
        })->values();

        $base = ServiceCycleDeviation::query();
        $confirmed = (clone $base)->whereIn('status', ['confirmed', 'resolved'])->get(['severity']);
        $completedCycles = ServiceCycleCommitment::where('status', 'completed')->count();
        $penalty = $confirmed->sum(fn ($item) => match ($item->severity) {
            'critical' => 12, 'high' => 7, default => 3,
        });
        $correctivePlans = ServiceCorrectivePlan::query()
            ->with([
                'owner:id,name', 'maintenancePlan:id,installation_id,name',
                'maintenancePlan.installation:id,project_reference,site_name,service_type',
                'deviations:id,category,severity,status,summary', 'actions.owner:id,name',
                'proposals.creator:id,name', 'events.actor:id,name',
            ])
            ->orderByRaw("case status when 'verification' then 0 when 'in_progress' then 1 when 'draft' then 2 else 3 end")
            ->orderBy('target_date')
            ->get()
            ->map(function (ServiceCorrectivePlan $plan) use ($effectiveness): array {
                $evaluation = $plan->status === 'verification'
                    ? $effectiveness->evaluate($plan)
                    : $plan->effectiveness_snapshot;

                return [
                    'id' => $plan->id, 'code' => $plan->code, 'title' => $plan->title,
                    'root_cause_category' => $plan->root_cause_category, 'root_cause_analysis' => $plan->root_cause_analysis,
                    'status' => $plan->status, 'target_date' => $plan->target_date->toDateString(),
                    'verification_cycles' => $plan->verification_cycles, 'owner' => $plan->owner,
                    'baseline_snapshot' => $plan->baseline_snapshot, 'effectiveness' => $evaluation,
                    'effectiveness_result' => $plan->effectiveness_result, 'effectiveness_note' => $plan->effectiveness_note,
                    'maintenance_plan' => [
                        'id' => $plan->maintenancePlan->id, 'name' => $plan->maintenancePlan->name,
                        'installation' => $plan->maintenancePlan->installation,
                    ],
                    'deviations' => $plan->deviations->map(fn ($deviation) => [
                        'id' => $deviation->id, 'reference' => 'DEV-'.str_pad((string) $deviation->id, 6, '0', STR_PAD_LEFT),
                        'category' => $deviation->category, 'severity' => $deviation->severity,
                        'status' => $deviation->status, 'summary' => __($deviation->summary),
                    ])->values(),
                    'actions' => $plan->actions->map(fn ($action) => [
                        'id' => $action->id, 'type' => $action->type, 'title' => $action->title,
                        'details' => $action->details, 'status' => $action->status,
                        'due_on' => $action->due_on->toDateString(), 'owner' => $action->owner,
                        'completion_note' => $action->completion_note, 'completed_at' => $action->completed_at?->toISOString(),
                    ])->values(),
                    'proposals' => $plan->proposals->map(fn ($proposal) => [
                        'id' => $proposal->id, 'type' => $proposal->type, 'title' => $proposal->title,
                        'rationale' => $proposal->rationale, 'payload' => $proposal->payload,
                        'status' => $proposal->status, 'created_at' => $proposal->created_at->toISOString(),
                        'creator' => $proposal->creator,
                    ])->values(),
                    'events' => $plan->events->map(fn ($event) => [
                        'id' => $event->id, 'event' => $event->event, 'from_status' => $event->from_status,
                        'to_status' => $event->to_status, 'note' => $event->note,
                        'created_at' => $event->created_at->toISOString(), 'actor' => $event->actor,
                    ])->values(),
                ];
            })->values();

        return Inertia::render('after-sales/quality', [
            'deviations' => $deviations,
            'correctivePlans' => $correctivePlans,
            'filters' => $filters,
            'summary' => [
                'open' => (clone $base)->where('status', 'open')->count(),
                'confirmed' => (clone $base)->where('status', 'confirmed')->count(),
                'overdue' => (clone $base)->whereIn('status', ['open', 'confirmed'])->whereDate('review_due_on', '<', today())->count(),
                'affected_assets' => DB::table('service_cycle_deviation_assets')
                    ->join('service_cycle_deviations', 'service_cycle_deviations.id', '=', 'service_cycle_deviation_assets.service_cycle_deviation_id')
                    ->whereIn('service_cycle_deviations.status', ['confirmed', 'resolved'])
                    ->distinct()->count('service_cycle_deviation_assets.installation_asset_id'),
                'quality_score' => $completedCycles > 0 ? max(0, round(100 - ($penalty / $completedCycles), 1)) : null,
                'completed_cycles' => $completedCycles,
                'active_corrective_plans' => ServiceCorrectivePlan::whereIn('status', ['draft', 'in_progress', 'verification'])->count(),
                'overdue_corrective_actions' => DB::table('service_corrective_actions')->join('service_corrective_plans', 'service_corrective_plans.id', '=', 'service_corrective_actions.service_corrective_plan_id')->whereIn('service_corrective_plans.status', ['draft', 'in_progress'])->whereIn('service_corrective_actions.status', ['open', 'in_progress'])->whereDate('service_corrective_actions.due_on', '<', today())->count(),
            ],
            'businessLines' => ServiceCycleDeviation::query()->distinct()->orderBy('business_line')->pluck('business_line'),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'canViewFinancials' => $canViewFinancials,
        ]);
    }

    public function analyse(Request $request, RecurringServiceQualityDetector $detector): RedirectResponse
    {
        $detected = 0;
        ServiceCycleCommitment::query()->where('status', 'completed')->orderBy('id')->eachById(
            function (ServiceCycleCommitment $commitment) use ($detector, &$detected): void {
                $detected += $detector->detect($commitment)->whereIn('status', ['open', 'confirmed'])->count();
            },
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Quality analysis completed. :count deviations require or retain review.', ['count' => $detected]),
        ]);
    }

    public function review(Request $request, ServiceCycleDeviation $deviation, RecurringServiceQualityWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['assign', 'confirm', 'dismiss', 'resolve', 'reopen'])],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'review_due_on' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => [Rule::requiredIf($request->input('action') !== 'assign'), 'nullable', 'string', 'min:5', 'max:2000'],
        ]);
        $workflow->review(
            $deviation,
            $data['action'],
            isset($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            $data['review_due_on'] ?? null,
            $data['note'] ?? __('Review responsibility updated.'),
            $request->user(),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('Quality review updated with a traceable event.')]);
    }
}
