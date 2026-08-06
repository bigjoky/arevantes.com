<?php

namespace App\Services\Supply;

use App\Models\PurchaseDocument;
use App\Models\PurchaseDocumentLine;
use App\Models\SupplierProduct;

class SupplierCatalogRecorder
{
    public function recordDocument(PurchaseDocument $document): void
    {
        $document->loadMissing('lines');
        $document->lines->each(fn (PurchaseDocumentLine $line) => $this->recordLine($line, $document));
    }

    public function recordLine(PurchaseDocumentLine $line, ?PurchaseDocument $document = null): void
    {
        $document ??= $line->document;
        if (! $document || ! in_array($document->type, ['purchase_rfq', 'purchase_order', 'supplier_invoice'], true)) {
            return;
        }
        if ($document->type === 'purchase_rfq' && $document->status !== 'quoted') {
            return;
        }

        $catalog = SupplierProduct::firstOrCreate(
            ['supplier_id' => $document->supplier_id, 'product_id' => $line->product_id],
            ['minimum_order_quantity' => 1, 'order_multiple' => 1, 'currency' => 'EUR', 'is_active' => true],
        );
        $catalog->update([
            'last_unit_price' => $line->unit_price,
            'lead_time_days' => $document->delivery_days ?? $catalog->lead_time_days,
        ]);
        $catalog->prices()->updateOrCreate(
            ['purchase_document_line_id' => $line->id],
            ['unit_price' => $line->unit_price, 'currency' => $catalog->currency, 'recorded_at' => $document->quoted_at ?? $document->issue_date ?? now()],
        );
    }
}
