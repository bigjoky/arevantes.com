<?php

namespace App\Http\Controllers;

use App\Models\PlanningScenario;
use App\Models\PlanningScenarioRequest;
use App\Models\User;
use App\Services\Planning\PlanningScenarioExceptionWorkflow;
use App\Services\Planning\PlanningScenarioMaterializationWorkflow;
use App\Services\Planning\PlanningScenarioOutcomeMonitor;
use App\Services\Planning\PlanningScenarioRequestWorkflow;
use App\Services\Security\SecurityEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanningScenarioRequestController extends Controller
{
    public function index(Request $request, PlanningScenarioRequestWorkflow $workflow, PlanningScenarioOutcomeMonitor $monitor): Response
    {
        $areas = $workflow->areasFor($request->user());
        abort_if($areas === [], 403);

        $requests = PlanningScenarioRequest::query()
            ->whereIn('area', $areas)
            ->with([
                'scenario:id,name,version,horizon_starts_on,horizon_ends_on', 'generator:id,name', 'confirmer:id,name',
                'preparer:id,name', 'authorizer:id,name', 'executor:id,name', 'promoter:id,name',
            ])
            ->orderByRaw("case status when 'draft' then 0 else 1 end")
            ->latest()
            ->get();
        $monitoring = $monitor->forMany($requests);

        return Inertia::render('planning-scenario-requests/index', [
            'areas' => $areas,
            'requests' => $requests->map(fn (PlanningScenarioRequest $scenarioRequest): array => [
                ...$scenarioRequest->toArray(),
                'outcome_monitoring' => $monitoring[$scenarioRequest->id],
            ]),
            'governance' => [
                'canAssignTasks' => Gate::allows('manage-installations'),
                'canManageReservations' => Gate::allows('manage-catalog'),
                'assignees' => Gate::allows('manage-installations')
                    ? User::query()->whereIn('role', ['admin', 'commercial', 'technician'])->orderBy('name')->get(['id', 'name', 'role'])
                    : [],
            ],
        ]);
    }

    public function generate(Request $request, PlanningScenario $scenario, PlanningScenarioRequestWorkflow $workflow, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate(['fingerprint' => ['required', 'string', 'size:64']]);
        $requests = $workflow->generate($scenario, $request->user(), $data['fingerprint']);
        $events->record('planning_scenario_requests_generated', $request->user(), [
            'planning_scenario_id' => $scenario->id,
            'areas' => $requests->pluck('area')->all(),
            'approved_fingerprint' => $data['fingerprint'],
            'non_committing' => true,
        ]);

        return redirect()->route('planning-scenario-requests.index')
            ->with('toast', ['type' => 'success', 'message' => __('Area requests generated as non-committing drafts.')]);
    }

    public function confirm(Request $request, PlanningScenarioRequest $scenarioRequest, PlanningScenarioRequestWorkflow $workflow, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate([
            'fingerprint' => ['required', 'string', 'size:64'],
            'confirmation_note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $confirmed = $workflow->confirm($scenarioRequest, $request->user(), $data['fingerprint'], $data['confirmation_note']);
        $events->record('planning_scenario_request_confirmed', $request->user(), [
            'planning_scenario_request_id' => $confirmed->id,
            'planning_scenario_id' => $confirmed->planning_scenario_id,
            'area' => $confirmed->area,
            'request_fingerprint' => $confirmed->request_fingerprint,
            'non_committing' => true,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Area request confirmed without creating operational resources.')]);
    }

    public function prepare(Request $request, PlanningScenarioRequest $scenarioRequest, PlanningScenarioMaterializationWorkflow $workflow, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate(['fingerprint' => ['required', 'string', 'size:64']]);
        $prepared = $workflow->prepare($scenarioRequest, $request->user(), $data['fingerprint']);
        $events->record('planning_scenario_materialization_prepared', $request->user(), [
            'planning_scenario_request_id' => $prepared->id,
            'area' => $prepared->area,
            'materialization_fingerprint' => $prepared->materialization_fingerprint,
            'idempotency_key' => $prepared->idempotency_key,
            'blocking_conflicts' => data_get($prepared->materialization_preview, 'state.summary.blocking_conflicts'),
            'non_committing' => true,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Materialization preview prepared without creating resources.')]);
    }

    public function authorizeMaterialization(Request $request, PlanningScenarioRequest $scenarioRequest, PlanningScenarioMaterializationWorkflow $workflow, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate([
            'fingerprint' => ['required', 'string', 'size:64'],
            'authorization_note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $authorized = $workflow->authorize($scenarioRequest, $request->user(), $data['fingerprint'], $data['authorization_note']);
        $events->record('planning_scenario_materialization_authorized', $request->user(), [
            'planning_scenario_request_id' => $authorized->id,
            'area' => $authorized->area,
            'materialization_fingerprint' => $authorized->materialization_fingerprint,
            'idempotency_key' => $authorized->idempotency_key,
            'non_committing' => true,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Materialization authorized without creating resources.')]);
    }

    public function execute(Request $request, PlanningScenarioRequest $scenarioRequest, PlanningScenarioMaterializationWorkflow $workflow, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate([
            'fingerprint' => ['required', 'string', 'size:64'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $executed = $workflow->execute($scenarioRequest, $request->user(), $data['fingerprint'], $data['idempotency_key']);
        $events->record('planning_scenario_request_executed', $request->user(), [
            'planning_scenario_request_id' => $executed->id,
            'area' => $executed->area,
            'execution_fingerprint' => $executed->execution_fingerprint,
            'idempotency_key' => $executed->idempotency_key,
            'result' => $executed->execution_result,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Authorized planning request executed once.')]);
    }

    public function promote(Request $request, PlanningScenarioRequest $scenarioRequest, PlanningScenarioMaterializationWorkflow $workflow, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate(['fingerprint' => ['required', 'string', 'size:64']]);
        $promoted = $workflow->promote($scenarioRequest, $request->user(), $data['fingerprint']);
        $events->record('planning_scenario_request_promoted', $request->user(), [
            'planning_scenario_request_id' => $promoted->id,
            'area' => $promoted->area,
            'execution_fingerprint' => $promoted->execution_fingerprint,
            'promotion_fingerprint' => $promoted->promotion_fingerprint,
            'result' => $promoted->promotion_result,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Planning drafts promoted to their native workflow.')]);
    }

    public function assignTasks(Request $request, PlanningScenarioRequest $scenarioRequest, PlanningScenarioExceptionWorkflow $workflow, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate([
            'assignee_id' => ['required', 'integer', Rule::exists('users', 'id')->whereIn('role', ['admin', 'commercial', 'technician'])],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
            'governance_note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $result = $workflow->assignOpenTasks($scenarioRequest, $request->user(), (int) $data['assignee_id'], $data['due_on'], $data['governance_note']);
        $events->record('planning_scenario_tasks_assigned', $request->user(), [
            'planning_scenario_request_id' => $scenarioRequest->id,
            ...$result,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Open planning tasks assigned with a target date.')]);
    }

    public function resolveReservations(Request $request, PlanningScenarioRequest $scenarioRequest, PlanningScenarioExceptionWorkflow $workflow, SecurityEventRecorder $events): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['renew', 'release'])],
            'reserved_until' => ['nullable', 'required_if:action,renew', 'date', 'after:today'],
            'governance_note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $result = $workflow->resolveExpiredReservations($scenarioRequest, $request->user(), $data['action'], $data['reserved_until'] ?? null, $data['governance_note']);
        $events->record($data['action'] === 'renew' ? 'planning_scenario_reservations_renewed' : 'planning_scenario_reservations_released', $request->user(), [
            'planning_scenario_request_id' => $scenarioRequest->id,
            ...$result,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => $data['action'] === 'renew' ? __('Expired planning reservations renewed.') : __('Expired planning reservations released.')]);
    }
}
