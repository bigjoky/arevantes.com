<?php

namespace App\Http\Controllers;

use App\Models\PurchaseApprovalRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseApprovalRuleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        PurchaseApprovalRule::create([...$this->validated($request), 'created_by' => $request->user()->id]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Purchase approval rule created.')]);
    }

    public function update(Request $request, PurchaseApprovalRule $purchaseApprovalRule): RedirectResponse
    {
        $purchaseApprovalRule->update($this->validated($request));

        return back()->with('toast', ['type' => 'success', 'message' => __('Purchase approval rule updated.')]);
    }

    public function destroy(PurchaseApprovalRule $purchaseApprovalRule): RedirectResponse
    {
        $purchaseApprovalRule->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Purchase approval rule deleted.')]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'product_type' => ['nullable', Rule::in(['product', 'service'])],
            'min_amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'max_amount' => ['nullable', 'numeric', 'gt:min_amount', 'max:999999999999'],
            'priority' => ['required', 'integer', 'between:1,1000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
