<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ReplenishmentPolicy;
use App\Models\Warehouse;
use App\Services\Supply\ReplenishmentPlanner;
use App\Services\Supply\ReplenishmentProcurement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReplenishmentController extends Controller
{
    public function index(ReplenishmentPlanner $planner): Response
    {
        $policies = ReplenishmentPolicy::with(['warehouse:id,code,name', 'product:id,sku,name'])
            ->orderBy('warehouse_id')
            ->orderBy('product_id')
            ->get();

        return Inertia::render('purchases/replenishment/index', [
            'policies' => $policies,
            'recommendations' => $planner->recommendations(),
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'code', 'name']),
            'products' => Product::where('is_active', true)->where('track_stock', true)->orderBy('name')->get(['id', 'sku', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ReplenishmentPolicy::create($this->validated($request));

        return back()->with('toast', ['type' => 'success', 'message' => __('Replenishment policy created.')]);
    }

    public function update(Request $request, ReplenishmentPolicy $policy): RedirectResponse
    {
        $policy->update($this->validated($request, $policy));

        return back()->with('toast', ['type' => 'success', 'message' => __('Replenishment policy updated.')]);
    }

    public function destroy(ReplenishmentPolicy $policy): RedirectResponse
    {
        $policy->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Replenishment policy removed.')]);
    }

    public function procure(Request $request, ReplenishmentProcurement $procurement): RedirectResponse
    {
        $data = $request->validate([
            'policies' => ['required', 'array', 'min:1'],
            'policies.*' => ['required', 'integer', 'distinct', 'exists:replenishment_policies,id'],
        ]);
        $documents = $procurement->createRequests($data['policies'], $request->user()->id);

        return $documents->count() === 1
            ? to_route('purchases.show', $documents->first())->with('toast', ['type' => 'success', 'message' => __('Replenishment RFQ created.')])
            : to_route('purchases.index')->with('toast', ['type' => 'success', 'message' => trans_choice(':count replenishment RFQ created.|:count replenishment RFQs created.', $documents->count(), ['count' => $documents->count()])]);
    }

    private function validated(Request $request, ?ReplenishmentPolicy $policy = null): array
    {
        $data = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where('is_active', true)->where('track_stock', true),
                Rule::unique('replenishment_policies')->where('warehouse_id', $request->input('warehouse_id'))->ignore($policy),
            ],
            'reorder_point' => ['required', 'numeric', 'min:0'],
            'target_stock' => ['required', 'numeric', 'gt:reorder_point'],
            'is_active' => ['boolean'],
        ]);

        return $data;
    }
}
