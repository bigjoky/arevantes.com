<?php

namespace App\Http\Controllers;

use App\Models\PurchaseDocument;
use App\Services\Documents\PurchaseOrderConversion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseComparisonController extends Controller
{
    public function show(string $batch): Response
    {
        $requests = PurchaseDocument::query()
            ->where('type', 'purchase_rfq')
            ->where('procurement_batch_id', $batch)
            ->with(['supplier:id,legal_name,trade_name', 'salesOrder:id,number', 'lines.product:id,name,sku'])
            ->orderBy('id')
            ->get();
        abort_if($requests->isEmpty(), 404);

        return Inertia::render('purchases/comparison', [
            'batch' => $batch,
            'salesOrder' => $requests->first()->salesOrder,
            'requests' => $requests,
        ]);
    }

    public function respond(Request $request, PurchaseDocument $purchaseDocument): RedirectResponse
    {
        abort_unless($purchaseDocument->type === 'purchase_rfq' && $purchaseDocument->procurement_batch_id && in_array($purchaseDocument->status, ['draft', 'sent', 'quoted'], true), 422);
        $data = $request->validate([
            'supplier_number' => ['nullable', 'string', 'max:255'],
            'delivery_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'valid_until' => ['nullable', 'date'],
            'quote_notes' => ['nullable', 'string', 'max:5000'],
            'prices' => ['required', 'array'],
            'prices.*' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($purchaseDocument, $data): void {
            $purchaseDocument = PurchaseDocument::lockForUpdate()->with('lines')->findOrFail($purchaseDocument->id);
            $subtotal = $tax = $amount = 0;
            foreach ($purchaseDocument->lines as $line) {
                $price = round((float) ($data['prices'][$line->id] ?? $line->unit_price), 2);
                $base = round((float) $line->quantity * $price * (1 - (float) $line->discount_percent / 100), 2);
                $lineTax = round($base * (float) $line->tax_percent / 100, 2);
                $line->update(['unit_price' => $price, 'subtotal' => $base, 'tax_amount' => $lineTax, 'total' => $base + $lineTax]);
                $subtotal += $base;
                $tax += $lineTax;
                $amount += $base + $lineTax;
            }
            $purchaseDocument->update([
                'supplier_number' => $data['supplier_number'] ?? null,
                'delivery_days' => $data['delivery_days'],
                'valid_until' => $data['valid_until'] ?? null,
                'quote_notes' => $data['quote_notes'] ?? null,
                'quoted_at' => now(),
                'status' => 'quoted',
                'subtotal' => round($subtotal, 2),
                'tax_amount' => round($tax, 2),
                'shipping_cost' => round((float) ($data['shipping_cost'] ?? 0), 2),
                'amount' => round($amount + (float) ($data['shipping_cost'] ?? 0), 2),
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Supplier quote registered.')]);
    }

    public function select(PurchaseDocument $purchaseDocument, PurchaseOrderConversion $conversion): RedirectResponse
    {
        abort_unless($purchaseDocument->procurement_batch_id, 422);
        $order = $conversion->convert($purchaseDocument, request()->user()->id, true);

        return to_route('purchases.show', $order)->with('toast', ['type' => 'success', 'message' => __('Offer selected and purchase order created.')]);
    }
}
