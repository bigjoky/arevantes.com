<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\PlanningScenario;
use App\Services\Planning\SharedPortfolioScenario;
use App\Services\Security\SecurityEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanningScenarioController extends Controller
{
    public function index(Request $request): Response
    {
        $scenarios = PlanningScenario::query()->with(['creator:id,name', 'baselineScenario:id,name,version'])->latest()->get();
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
        ]);
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
        ]);
        $scenario = $planner->freeze($data, $request->user());
        $events->record('planning_scenario_frozen', $request->user(), [
            'planning_scenario_id' => $scenario->id,
            'fingerprint' => $scenario->fingerprint,
            'opportunities' => $scenario->result_snapshot['summary']['opportunities'],
        ]);

        return redirect()->route('planning-scenarios.index', ['scenario' => $scenario->id])
            ->with('toast', ['type' => 'success', 'message' => __('Scenario frozen without committing resources.')]);
    }
}
