<?php

namespace App\Http\Controllers;

use App\Models\ServiceCorrectiveAction;
use App\Models\ServiceCorrectivePlan;
use App\Models\ServiceCorrectiveProposal;
use App\Models\ServiceCycleDeviation;
use App\Services\AfterSales\RecurringServiceCorrectivePlanWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurringServiceCorrectivePlanController extends Controller
{
    public function store(Request $request, ServiceCycleDeviation $deviation, RecurringServiceCorrectivePlanWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'deviation_ids' => ['required', 'array', 'min:1', 'max:20'],
            'deviation_ids.*' => ['required', 'integer', 'distinct', 'exists:service_cycle_deviations,id'],
            'title' => ['required', 'string', 'max:255'],
            'root_cause_category' => ['required', Rule::in(['process', 'training', 'materials', 'template', 'scope', 'scheduling', 'external', 'unknown'])],
            'root_cause_analysis' => ['required', 'string', 'min:10', 'max:5000'],
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'target_date' => ['required', 'date', 'after_or_equal:today'],
            'verification_cycles' => ['required', 'integer', 'between:1,12'],
            'action_type' => ['required', Rule::in(['corrective', 'preventive'])],
            'action_title' => ['required', 'string', 'max:255'],
            'action_details' => ['nullable', 'string', 'max:3000'],
            'action_owner_id' => ['required', 'integer', 'exists:users,id'],
            'action_due_on' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $workflow->create($deviation, array_map('intval', $data['deviation_ids']), [
            'title' => $data['title'], 'root_cause_category' => $data['root_cause_category'],
            'root_cause_analysis' => $data['root_cause_analysis'], 'owner_id' => $data['owner_id'],
            'target_date' => $data['target_date'], 'verification_cycles' => $data['verification_cycles'],
        ], [
            'type' => $data['action_type'], 'title' => $data['action_title'], 'details' => $data['action_details'] ?? null,
            'owner_id' => $data['action_owner_id'], 'due_on' => $data['action_due_on'],
        ], $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Corrective action plan prepared with a frozen baseline.')]);
    }

    public function transition(Request $request, ServiceCorrectivePlan $correctivePlan, RecurringServiceCorrectivePlanWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['activate', 'start_verification', 'verify_effective', 'verify_ineffective', 'close', 'reopen'])],
            'note' => ['required', 'string', 'min:5', 'max:3000'],
        ]);
        $workflow->transition($correctivePlan, $data['action'], $data['note'], $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Corrective plan updated with a traceable decision.')]);
    }

    public function storeAction(Request $request, ServiceCorrectivePlan $correctivePlan, RecurringServiceCorrectivePlanWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['corrective', 'preventive'])],
            'title' => ['required', 'string', 'max:255'], 'details' => ['nullable', 'string', 'max:3000'],
            'owner_id' => ['required', 'integer', 'exists:users,id'], 'due_on' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $workflow->addAction($correctivePlan, $data, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Corrective-plan action added.')]);
    }

    public function updateAction(Request $request, ServiceCorrectiveAction $correctiveAction, RecurringServiceCorrectivePlanWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'completed', 'cancelled'])],
            'completion_note' => ['nullable', 'string', 'max:3000'],
        ]);
        $workflow->updateAction($correctiveAction, $data['status'], $data['completion_note'] ?? null, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Corrective action updated.')]);
    }

    public function storeProposal(Request $request, ServiceCorrectivePlan $correctivePlan, RecurringServiceCorrectivePlanWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['work_order_template', 'preventive_stock', 'maintenance_scope', 'renewal'])],
            'title' => ['required', 'string', 'max:255'], 'rationale' => ['required', 'string', 'min:10', 'max:3000'],
            'proposed_change' => ['required', 'string', 'min:5', 'max:5000'],
        ]);
        $workflow->addProposal($correctivePlan, [
            'type' => $data['type'], 'title' => $data['title'], 'rationale' => $data['rationale'],
            'payload' => ['proposed_change' => $data['proposed_change']],
        ], $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Change proposal prepared without applying it.')]);
    }

    public function decideProposal(Request $request, ServiceCorrectiveProposal $correctiveProposal, RecurringServiceCorrectivePlanWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->can($workflow->decisionAbility($correctiveProposal->type)), 403);
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'return', 'resubmit'])],
            'note' => ['required', 'string', 'min:10', 'max:3000'],
            'proposed_change' => [Rule::requiredIf($request->input('action') === 'resubmit'), 'nullable', 'string', 'min:5', 'max:5000'],
        ]);
        $workflow->decideProposal(
            $correctiveProposal, $data['action'], $data['note'], $data['proposed_change'] ?? null, $request->user(),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('Improvement proposal decision recorded without applying downstream changes.')]);
    }
}
