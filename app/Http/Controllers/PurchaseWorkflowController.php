<?php

namespace App\Http\Controllers;

use App\Models\PurchaseDocument;
use App\Services\Documents\PurchaseWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseWorkflowController extends Controller
{
    public function schedule(Request $request, PurchaseDocument $purchaseDocument, PurchaseWorkflow $workflow): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'purchase_order' && ! in_array($purchaseDocument->status, ['cancelled', 'closed'], true), 422);
        $data = $request->validate(['expected_delivery_date' => ['nullable', 'date']]);
        $old = $purchaseDocument->expected_delivery_date?->toDateString();
        $purchaseDocument->update(['expected_delivery_date' => $data['expected_delivery_date'] ?? null]);
        $workflow->record($purchaseDocument, 'delivery_rescheduled', $request->user()->id, $purchaseDocument->status, $purchaseDocument->status, [
            'from' => $old,
            'to' => $data['expected_delivery_date'] ?? null,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Expected delivery date updated.')]);
    }

    public function cancel(Request $request, PurchaseDocument $purchaseDocument, PurchaseWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $workflow->cancel($purchaseDocument, $request->user(), $data['reason']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Purchase document cancelled.')]);
    }

    public function close(Request $request, PurchaseDocument $purchaseDocument, PurchaseWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $workflow->close($purchaseDocument, $request->user(), $data['reason']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Purchase order closed.')]);
    }

    public function reopen(Request $request, PurchaseDocument $purchaseDocument, PurchaseWorkflow $workflow): RedirectResponse
    {
        $workflow->reopen($purchaseDocument, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Purchase order reopened.')]);
    }
}
