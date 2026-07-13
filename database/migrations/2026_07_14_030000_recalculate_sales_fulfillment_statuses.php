<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales_documents')->where('type', 'order')->orderBy('id')->each(function ($order): void {
            $ordered = (float) DB::table('sales_document_lines')->where('sales_document_id', $order->id)->sum('quantity');
            $delivered = (float) DB::table('sales_document_lines')
                ->join('sales_documents', 'sales_documents.id', '=', 'sales_document_lines.sales_document_id')
                ->where('sales_documents.source_document_id', $order->id)
                ->where('sales_documents.type', 'delivery_note')
                ->where('sales_documents.status', 'delivered')
                ->sum('sales_document_lines.quantity');

            DB::table('sales_documents')->where('id', $order->id)->update([
                'fulfillment_status' => $ordered > 0 && $delivered >= $ordered ? 'complete' : ($delivered > 0 ? 'partial' : 'pending'),
            ]);
        });

        DB::table('sales_documents')->where('type', 'delivery_note')->update([
            'fulfillment_status' => DB::raw("case when status = 'delivered' then 'complete' else 'pending' end"),
        ]);
    }

    public function down(): void
    {
        // The previous fulfillment status cannot be reconstructed reliably.
    }
};
