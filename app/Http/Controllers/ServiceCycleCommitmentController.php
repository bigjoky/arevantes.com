<?php

namespace App\Http\Controllers;

use App\Models\MaintenancePlan;
use App\Services\AfterSales\ServiceCycleCommitmentWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceCycleCommitmentController extends Controller
{
    public function store(Request $request, MaintenancePlan $plan, ServiceCycleCommitmentWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'plan_version' => ['required', 'integer', 'min:1'],
            'planned_travel_km' => ['required', 'numeric', 'between:0,100000'],
            'approval_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $workflow->approve($plan, (int) $data['plan_version'], (float) $data['planned_travel_km'], $data['approval_note'], $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Service capacity and cycle economics approved. No work order or customer commitment was created.')]);
    }
}
