<?php

namespace App\Http\Controllers;

use App\Models\PurchaseSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'matching_amount_tolerance' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'matching_percent_tolerance' => ['required', 'numeric', 'between:0,100'],
            'shipping_tax_percent' => ['required', 'numeric', 'between:0,100'],
            'order_approval_threshold' => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ]);
        PurchaseSetting::current()->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => __('Purchase matching settings updated.')]);
    }
}
