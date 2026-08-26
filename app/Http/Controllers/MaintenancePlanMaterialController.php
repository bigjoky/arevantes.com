<?php

namespace App\Http\Controllers;

use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanMaterial;
use App\Services\AfterSales\MaintenancePlanLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MaintenancePlanMaterialController extends Controller
{
    public function store(Request $request, MaintenancePlan $plan, MaintenancePlanLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('is_active', true)->where('track_stock', true))],
            'quantity_per_visit' => ['required', 'numeric', 'between:0.001,999999999.999'],
            'expected_usage_percent' => ['required', 'numeric', 'between:0,100'],
            'minimum_stock' => ['required', 'numeric', 'between:0,999999999.999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'change_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $changeNote = $data['change_note'];
        unset($data['change_note']);

        DB::transaction(function () use ($plan, $data, $changeNote, $request, $lifecycle): void {
            $plan->preventiveMaterials()->updateOrCreate(['product_id' => $data['product_id']], $data);
            $lifecycle->revise($plan, [], null, $changeNote, $request->user());
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Preventive material forecast updated. No stock has been reserved.')]);
    }

    public function destroy(Request $request, MaintenancePlan $plan, MaintenancePlanMaterial $material, MaintenancePlanLifecycle $lifecycle): RedirectResponse
    {
        abort_unless($material->maintenance_plan_id === $plan->id, 404);
        $data = $request->validate(['change_note' => ['required', 'string', 'min:5', 'max:2000']]);

        DB::transaction(function () use ($plan, $material, $data, $request, $lifecycle): void {
            $material->delete();
            $lifecycle->revise($plan, [], null, $data['change_note'], $request->user());
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Preventive material removed from the forecast.')]);
    }
}
