<?php

namespace App\Services\Documents;

use App\Models\PurchaseDocument;
use Illuminate\Support\Facades\DB;

class PurchaseOrderConversion
{
    public function __construct(private DocumentNumber $numbers) {}

    public function convert(PurchaseDocument $request, int $userId, bool $requireQuote = false): PurchaseDocument
    {
        return DB::transaction(function () use ($request, $userId, $requireQuote): PurchaseDocument {
            $request = PurchaseDocument::lockForUpdate()->with('lines')->findOrFail($request->id);
            abort_unless($request->type === 'purchase_rfq' && in_array($request->status, ['draft', 'sent', 'quoted'], true), 422);
            abort_if($requireQuote && $request->status !== 'quoted', 422, __('Register the supplier quote before selecting it.'));
            $existing = PurchaseDocument::where('type', 'purchase_order')->where('source_document_id', $request->id)->first();
            if ($existing) {
                return $existing;
            }

            $issued = $this->numbers->next('purchase_order', now());
            $order = PurchaseDocument::create([
                'type' => 'purchase_order',
                'source_document_id' => $request->id,
                'installation_id' => $request->installation_id,
                'work_order_id' => $request->work_order_id,
                'sales_document_id' => $request->sales_document_id,
                'procurement_batch_id' => $request->procurement_batch_id,
                'supplier_id' => $request->supplier_id,
                'document_series_id' => $issued['series']->id,
                'created_by' => $userId,
                'number' => $issued['number'],
                'status' => 'ordered',
                'issue_date' => now()->toDateString(),
                'delivery_days' => $request->delivery_days,
                'subtotal' => $request->subtotal,
                'tax_amount' => $request->tax_amount,
                'shipping_cost' => $request->shipping_cost,
                'amount' => $request->amount,
                'notes' => $request->notes,
                'quote_notes' => $request->quote_notes,
                'locked_at' => now(),
            ]);
            $order->lines()->createMany($request->lines->map(fn ($line) => $line->only(['product_id', 'position', 'description', 'quantity', 'unit_price', 'estimated_unit_price', 'discount_percent', 'tax_percent', 'subtotal', 'tax_amount', 'total']))->all());
            $request->update(['status' => 'accepted', 'is_selected' => true, 'locked_at' => now()]);
            if ($request->procurement_batch_id) {
                PurchaseDocument::where('type', 'purchase_rfq')
                    ->where('procurement_batch_id', $request->procurement_batch_id)
                    ->whereKeyNot($request->id)
                    ->whereIn('status', ['draft', 'sent', 'quoted'])
                    ->update(['status' => 'rejected', 'is_selected' => false, 'locked_at' => now()]);
            }

            return $order;
        });
    }
}
