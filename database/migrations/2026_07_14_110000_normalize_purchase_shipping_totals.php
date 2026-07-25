<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_documents')
            ->where('shipping_cost', '!=', 0)
            ->orderBy('id')
            ->eachById(function ($document): void {
                $lines = DB::table('purchase_document_lines')
                    ->where('purchase_document_id', $document->id)
                    ->get(['subtotal', 'tax_amount']);
                $shippingCost = (float) $document->shipping_cost;
                $subtotal = round((float) $lines->sum('subtotal') + $shippingCost, 2);
                $tax = round((float) $lines->sum('tax_amount') + $shippingCost * 0.21 + 1e-9, 2);

                DB::table('purchase_documents')->where('id', $document->id)->update([
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax,
                    'amount' => round($subtotal + $tax, 2),
                ]);
            });
    }

    public function down(): void
    {
        // Historical totals cannot be safely restored after later documents exist.
    }
};
