<?php

namespace App\Http\Controllers;

use App\Models\CrmSetting;
use App\Models\Opportunity;
use App\Services\Crm\OpportunitySalesCycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmSettingsController extends Controller
{
    public function update(Request $request, OpportunitySalesCycle $salesCycle): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $rules = [
            'stale_after_days' => ['required', 'integer', 'between:1,365'],
            'lead_response_sla_minutes' => ['required', 'integer', 'between:15,10080'],
            'lead_response_escalation_minutes' => ['required', 'integer', 'between:15,10080'],
        ];
        foreach (CrmSetting::WEIGHT_FIELDS as $field) {
            $rules[$field] = ['required', 'integer', 'between:0,100'];
        }
        $data = $request->validate($rules);

        if (collect(CrmSetting::WEIGHT_FIELDS)->sum(fn (string $field) => (int) $data[$field]) !== 100) {
            throw ValidationException::withMessages([
                'weights' => __('Scoring weights must add up to 100 points.'),
            ]);
        }

        DB::transaction(function () use ($data, $salesCycle): void {
            CrmSetting::current()->update($data);
            $salesCycle->forgetSettings();
            Opportunity::query()->whereNotIn('stage', ['won', 'lost'])->chunkById(
                200,
                fn ($opportunities) => $opportunities->each(fn (Opportunity $opportunity) => $salesCycle->refreshScore($opportunity)),
            );
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('CRM settings updated and active scores recalculated.')]);
    }
}
