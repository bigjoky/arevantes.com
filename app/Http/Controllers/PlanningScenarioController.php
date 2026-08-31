<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\PlanningCalibrationEvaluation;
use App\Models\PlanningCalibrationGovernanceReview;
use App\Models\PlanningCalibrationProposal;
use App\Models\PlanningScenario;
use App\Services\Planning\PlanningCalibrationEvaluationService;
use App\Services\Planning\PlanningCalibrationGovernanceService;
use App\Services\Planning\PlanningCalibrationProposalService;
use App\Services\Planning\PlanningScenarioApproval;
use App\Services\Planning\PlanningScenarioIntegrity;
use App\Services\Planning\PlanningScenarioLearning;
use App\Services\Planning\PlanningScenarioLifecycle;
use App\Services\Planning\SharedPortfolioScenario;
use App\Services\Security\SecurityEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanningScenarioController extends Controller
{
    public function index(Request $request, PlanningScenarioIntegrity $integrity, PlanningScenarioLearning $learning): Response
    {
        $scenarios = PlanningScenario::query()->with([
            'creator:id,name', 'approver:id,name', 'closer:id,name', 'baselineScenario:id,name,version',
            'reviews.creator:id,name',
            'actionRequests:id,planning_scenario_id,area,status',
        ])->latest()->get();
        $scenarios->each(function (PlanningScenario $scenario) use ($integrity, $learning): void {
            $integrityValid = $integrity->matches($scenario);
            $referenceFresh = $scenario->reference_valid_until?->isFuture() ?? false;
            $scenario->setAttribute('approval_eligibility', [
                'can_approve' => $scenario->status === 'frozen' && $integrityValid && $referenceFresh,
                'integrity_valid' => $integrityValid,
                'reference_fresh' => $referenceFresh,
            ]);
            $scenario->setAttribute('learning_summary', $learning->for($scenario));
        });
        $selected = $scenarios->firstWhere('id', $request->integer('scenario')) ?: $scenarios->first();

        return Inertia::render('planning-scenarios/index', [
            'scenarios' => $scenarios,
            'selectedScenario' => $selected,
            'defaults' => [
                'horizon_starts_on' => today()->toDateString(),
                'horizon_ends_on' => today()->addDays(90)->toDateString(),
            ],
            'opportunities' => Opportunity::query()
                ->whereNotIn('stage', ['won', 'lost'])
                ->with(['customer:id,first_name,last_name,company_name', 'installation:id,service_type', 'quote:id,opportunity_id,number,amount'])
                ->orderBy('expected_close_on')
                ->orderBy('code')
                ->get(['id', 'person_id', 'installation_id', 'code', 'title', 'stage', 'probability', 'estimated_value', 'expected_close_on']),
            'calibrationProposals' => PlanningCalibrationProposal::query()
                ->with([
                    'creator:id,name', 'decider:id,name',
                    'evaluations.scenario:id,name,version', 'evaluations.creator:id,name', 'evaluations.decider:id,name',
                    'governanceReviews.creator:id,name', 'governanceReviews.decider:id,name',
                    'governanceReviews.replacementProposal:id,version', 'supersededBy:id,version',
                ])
                ->latest('id')
                ->limit(50)
                ->get()
                ->each->setAttribute('auto_apply', false),
        ]);
    }

    public function generateCalibrationGovernance(Request $request, PlanningCalibrationGovernanceService $governance, SecurityEventRecorder $events): RedirectResponse
    {
        $created = $governance->generate($request->user());
        $events->record('planning_calibration_governance_generated', $request->user(), [
            'created' => $created->count(),
            'planning_calibration_governance_review_ids' => $created->pluck('id')->all(),
            'minimum_sample_size' => PlanningCalibrationGovernanceService::MINIMUM_SAMPLE_SIZE,
            'auto_apply' => false,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $created->isEmpty()
                ? __('No calibration series has enough new decided evaluations.')
                : __('Calibration governance reviews generated from decided evaluations.'),
        ]);
    }

    public function decideCalibrationGovernance(Request $request, PlanningCalibrationGovernanceReview $review, PlanningCalibrationGovernanceService $governance, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['retain', 'supersede', 'insufficient'])],
            'replacement_proposal_id' => ['nullable', 'required_if:decision,supersede', 'integer', 'exists:planning_calibration_proposals,id'],
            'decision_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $decided = $governance->decide(
            $review,
            $request->user(),
            $data['decision'],
            $data['decision_note'],
            isset($data['replacement_proposal_id']) ? (int) $data['replacement_proposal_id'] : null,
        );
        $events->record('planning_calibration_governance_decided', $request->user(), [
            'planning_calibration_governance_review_id' => $decided->id,
            'planning_calibration_proposal_id' => $decided->planning_calibration_proposal_id,
            'recommendation' => $decided->recommendation,
            'decision' => $decided->decision,
            'replacement_proposal_id' => $decided->replacement_proposal_id,
            'fingerprint' => $decided->fingerprint,
            'auto_apply' => false,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Calibration version governance recorded without altering historical scenarios.')]);
    }

    public function generateCalibrationEvaluations(Request $request, PlanningCalibrationEvaluationService $evaluations, SecurityEventRecorder $events): RedirectResponse
    {
        $created = $evaluations->generate($request->user());
        $events->record('planning_calibration_evaluations_generated', $request->user(), [
            'created' => $created->count(),
            'planning_calibration_evaluation_ids' => $created->pluck('id')->all(),
            'auto_apply' => false,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $created->isEmpty()
                ? __('No new applied calibration is ready for evaluation.')
                : __('Applied calibration outcomes evaluated from immutable closures.'),
        ]);
    }

    public function decideCalibrationEvaluation(Request $request, PlanningCalibrationEvaluation $evaluation, PlanningCalibrationEvaluationService $evaluations, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['retain', 'revise', 'retire'])],
            'decision_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $decided = $evaluations->decide($evaluation, $request->user(), $data['decision'], $data['decision_note']);
        $events->record('planning_calibration_evaluation_decided', $request->user(), [
            'planning_calibration_evaluation_id' => $decided->id,
            'planning_calibration_proposal_id' => $decided->planning_calibration_proposal_id,
            'planning_scenario_id' => $decided->planning_scenario_id,
            'recommendation' => $decided->recommendation,
            'decision' => $decided->decision,
            'fingerprint' => $decided->fingerprint,
            'auto_apply' => false,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Calibration evaluation decision recorded without altering prior evidence.')]);
    }

    public function generateCalibrations(Request $request, PlanningCalibrationProposalService $calibrations, SecurityEventRecorder $events): RedirectResponse
    {
        $created = $calibrations->generate($request->user());
        $events->record('planning_calibrations_generated', $request->user(), [
            'created' => $created->count(),
            'planning_calibration_proposal_ids' => $created->pluck('id')->all(),
            'minimum_sample_size' => PlanningCalibrationProposalService::MINIMUM_SAMPLE_SIZE,
            'auto_apply' => false,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $created->isEmpty()
                ? __('No new calibration pattern meets the minimum evidence threshold.')
                : __('Calibration proposals generated from comparable closed scenarios.'),
        ]);
    }

    public function decideCalibration(Request $request, PlanningCalibrationProposal $calibration, PlanningCalibrationProposalService $calibrations, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'decision_note' => ['required', 'string', 'min:10', 'max:2000'],
            'accepted_value' => ['nullable', 'required_if:decision,accepted', 'numeric', 'min:0', 'max:1000'],
        ]);
        $decided = $calibrations->decide($calibration, $request->user(), $data['decision'], $data['decision_note'], $data['accepted_value'] ?? null);
        $events->record('planning_calibration_decided', $request->user(), [
            'planning_calibration_proposal_id' => $decided->id,
            'status' => $decided->status,
            'version' => $decided->version,
            'business_line' => $decided->business_line,
            'source_fingerprint' => $decided->source_fingerprint,
            'assumption_key' => $decided->assumption_key,
            'accepted_value' => $decided->accepted_value,
            'auto_apply' => false,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Calibration decision recorded without changing scenario assumptions.')]);
    }

    public function store(Request $request, SharedPortfolioScenario $planner, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scenario_kind' => ['required', Rule::in(['base', 'risk', 'alternative'])],
            'baseline_scenario_id' => ['nullable', 'required_unless:scenario_kind,base', 'integer', 'exists:planning_scenarios,id'],
            'horizon_starts_on' => ['required', 'date'],
            'horizon_ends_on' => ['required', 'date', 'after_or_equal:horizon_starts_on'],
            'opportunity_ids' => ['required', 'array', 'min:1', 'max:50'],
            'opportunity_ids.*' => ['required', 'integer', 'distinct', 'exists:opportunities,id'],
            'capacity_hours_per_opportunity' => ['required', 'numeric', 'min:0', 'max:1000'],
            'material_cost_percent_without_quote' => ['required', 'numeric', 'min:0', 'max:100'],
            'collection_delay_days' => ['required', 'integer', 'min:0', 'max:365'],
            'calibration_proposal_id' => ['nullable', 'integer', 'exists:planning_calibration_proposals,id'],
            'calibration_previous_value' => ['nullable', 'required_with:calibration_proposal_id', 'numeric', 'min:0', 'max:1000'],
        ]);
        $scenario = $planner->freeze($data, $request->user());
        $events->record('planning_scenario_frozen', $request->user(), [
            'planning_scenario_id' => $scenario->id,
            'fingerprint' => $scenario->fingerprint,
            'opportunities' => $scenario->result_snapshot['summary']['opportunities'],
            'planning_calibration_proposal_id' => data_get($scenario->assumptions, 'calibration_reference.proposal_id'),
        ]);

        return redirect()->route('planning-scenarios.index', ['scenario' => $scenario->id])
            ->with('toast', ['type' => 'success', 'message' => __('Scenario frozen without committing resources.')]);
    }

    public function approve(Request $request, PlanningScenario $scenario, PlanningScenarioApproval $approval, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate([
            'fingerprint' => ['required', 'string', 'size:64'],
            'approval_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $approved = $approval->approve($scenario, $request->user(), $data['fingerprint'], $data['approval_note'] ?? null);
        $events->record('planning_scenario_approved', $request->user(), [
            'planning_scenario_id' => $approved->id,
            'fingerprint' => $approved->approved_fingerprint,
            'reference_valid_until' => $approved->reference_valid_until?->toIso8601String(),
            'non_committing' => true,
        ]);

        return redirect()->route('planning-scenarios.index', ['scenario' => $approved->id])
            ->with('toast', ['type' => 'success', 'message' => __('Scenario approved without committing resources.')]);
    }

    public function review(Request $request, PlanningScenario $scenario, PlanningScenarioLifecycle $lifecycle, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate([
            'approved_fingerprint' => ['required', 'string', 'size:64'],
            'explanation' => ['required', 'string', 'min:10', 'max:3000'],
        ]);
        $review = $lifecycle->review($scenario, $request->user(), $data['approved_fingerprint'], $data['explanation']);
        $events->record('planning_scenario_review_recorded', $request->user(), [
            'planning_scenario_id' => $scenario->id,
            'planning_scenario_review_id' => $review->id,
            'sequence' => $review->sequence,
            'fingerprint' => $review->fingerprint,
            'summary' => $review->outcome_snapshot['summary'],
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Scenario review recorded with a frozen outcome snapshot.')]);
    }

    public function close(Request $request, PlanningScenario $scenario, PlanningScenarioLifecycle $lifecycle, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate([
            'approved_fingerprint' => ['required', 'string', 'size:64'],
            'review_fingerprint' => ['required', 'string', 'size:64'],
            'closure_note' => ['required', 'string', 'min:10', 'max:3000'],
            'acknowledge_exceptions' => ['required', 'accepted'],
        ]);
        $closed = $lifecycle->close($scenario, $request->user(), $data['approved_fingerprint'], $data['review_fingerprint'], $data['closure_note']);
        $events->record('planning_scenario_closed', $request->user(), [
            'planning_scenario_id' => $closed->id,
            'closure_fingerprint' => $closed->closure_fingerprint,
            'summary' => $closed->closure_snapshot['summary'],
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Scenario horizon closed without altering native resources.')]);
    }
}
