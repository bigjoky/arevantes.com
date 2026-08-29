<?php

namespace App\Http\Controllers;

use App\Models\ServiceCorrectiveProposal;
use App\Services\AfterSales\RecurringServiceCorrectivePlanWorkflow;
use App\Services\AfterSales\RecurringServiceImprovementApplicator;
use App\Services\AfterSales\RecurringServiceImprovementMonitor;
use App\Services\AfterSales\RecurringServiceProposalConflictDetector;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecurringServiceImprovementController extends Controller
{
    public function __invoke(
        Request $request,
        RecurringServiceCorrectivePlanWorkflow $workflow,
        RecurringServiceProposalConflictDetector $conflicts,
        RecurringServiceImprovementApplicator $applicator,
        RecurringServiceImprovementMonitor $monitor,
    ): Response {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'approved', 'applied', 'rejected', 'all'])],
        ]);
        $status = $filters['status'] ?? 'open';
        $types = $workflow->decisionTypesFor($request->user());

        abort_if($types === [], 403);

        $accessible = ServiceCorrectiveProposal::query()->whereIn('type', $types);
        $query = (clone $accessible)
            ->with([
                'creator:id,name', 'decider:id,name', 'applier:id,name', 'successor:id,title', 'superseder:id,name',
                'plan:id,maintenance_plan_id,code,title,status,verification_cycles,baseline_snapshot',
                'plan.maintenancePlan.template',
                'plan.maintenancePlan.preventiveMaterials',
                'plan.maintenancePlan.renewalOpportunity',
                'plan.maintenancePlan.installation:id,project_reference,site_name',
            ])
            ->when($status === 'open', fn ($query) => $query->whereIn('status', ['prepared', 'returned']))
            ->when($status === 'approved', fn ($query) => $query->where('status', 'approved'))
            ->when($status === 'applied', fn ($query) => $query->where('status', 'applied'))
            ->when($status === 'rejected', fn ($query) => $query->where('status', 'rejected'))
            ->orderByRaw("case status when 'prepared' then 0 when 'returned' then 1 when 'approved' then 2 when 'applied' then 3 else 4 end")
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ServiceCorrectiveProposal $proposal): array => $this->present($proposal, $conflicts, $applicator, $monitor));

        $prepared = (clone $accessible)
            ->where('status', 'prepared')
            ->with([
                'plan.maintenancePlan.template',
                'plan.maintenancePlan.preventiveMaterials',
                'plan.maintenancePlan.renewalOpportunity',
            ])->get();

        return Inertia::render('after-sales/improvements', [
            'proposals' => $query,
            'filters' => ['status' => $status],
            'summary' => [
                'open' => (clone $accessible)->whereIn('status', ['prepared', 'returned'])->count(),
                'approved' => (clone $accessible)->where('status', 'approved')->count(),
                'applied' => (clone $accessible)->where('status', 'applied')->count(),
                'rejected' => (clone $accessible)->where('status', 'rejected')->count(),
                'conflicting' => $prepared->filter(fn (ServiceCorrectiveProposal $proposal) => $conflicts->evaluate($proposal)['has_conflict'])->count(),
            ],
            'canManageOperations' => $request->user()->can('manage-operations'),
            'successorCandidates' => (clone $accessible)
                ->where('status', 'applied')
                ->whereNull('superseded_at')
                ->orderByDesc('applied_at')
                ->get(['id', 'service_corrective_plan_id', 'type', 'title', 'applied_at'])
                ->map(fn (ServiceCorrectiveProposal $candidate): array => [
                    'id' => $candidate->id,
                    'plan_id' => $candidate->service_corrective_plan_id,
                    'type' => $candidate->type,
                    'title' => $candidate->title,
                    'applied_at' => $candidate->applied_at?->toISOString(),
                ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function present(
        ServiceCorrectiveProposal $proposal,
        RecurringServiceProposalConflictDetector $conflicts,
        RecurringServiceImprovementApplicator $applicator,
        RecurringServiceImprovementMonitor $monitor,
    ): array {
        $applicationPreview = $proposal->status === 'approved' ? $applicator->preview($proposal) : null;

        return [
            'id' => $proposal->id,
            'type' => $proposal->type,
            'title' => $proposal->title,
            'rationale' => $proposal->rationale,
            'payload' => $proposal->payload,
            'status' => $proposal->status,
            'created_at' => $proposal->created_at->toISOString(),
            'creator' => $proposal->creator,
            'target_type' => $proposal->target_type,
            'target_version' => $proposal->target_version,
            'conflict' => in_array($proposal->status, ['prepared', 'returned'], true)
                ? $conflicts->evaluate($proposal)
                : ($applicationPreview['conflict'] ?? null),
            'decision_note' => $proposal->decision_note,
            'decider' => $proposal->decider,
            'decided_at' => $proposal->decided_at?->toISOString(),
            'resubmission_count' => $proposal->resubmission_count,
            'can_decide' => true,
            'application_preview' => $applicationPreview,
            'application_note' => $proposal->application_note,
            'application_result' => $proposal->application_result,
            'applier' => $proposal->applier,
            'applied_at' => $proposal->applied_at?->toISOString(),
            'monitoring' => $proposal->status === 'applied' ? $monitor->summarize($proposal) : null,
            'supersession_note' => $proposal->supersession_note,
            'superseder' => $proposal->superseder,
            'superseded_at' => $proposal->superseded_at?->toISOString(),
            'successor' => $proposal->successor,
            'plan' => [
                'id' => $proposal->plan->id,
                'code' => $proposal->plan->code,
                'title' => $proposal->plan->title,
                'maintenance_plan' => [
                    'name' => $proposal->plan->maintenancePlan->name,
                    'installation' => $proposal->plan->maintenancePlan->installation,
                ],
            ],
        ];
    }
}
