<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Supply\ReplenishmentTargetWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReplenishmentTargetController extends Controller
{
    public function store(Request $request, Product $product, ReplenishmentTargetWorkflow $workflow): RedirectResponse
    {
        abort_unless($product->is_active && $product->track_stock, 422, __('Only active stock products can receive replenishment objectives.'));
        $data = $request->validate([
            'target_quantity' => ['required', 'numeric', 'between:0.001,99999999999.999'],
            'approval_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $workflow->approve($product, (float) $data['target_quantity'], $data['approval_note'], $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Preventive replenishment objective approved. It remains separate from reservations and purchase orders.')]);
    }
}
