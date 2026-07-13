<?php

namespace App\Services\Documents;

use App\Models\PurchaseInboxDocument;
use App\Models\Supplier;

class PurchaseInboxAutomation
{
    public function __construct(private SupplierInvoiceExtraction $extraction) {}

    public function extractAndClassify(PurchaseInboxDocument $document): bool
    {
        if (! $this->extraction->extract($document)) {
            return false;
        }

        $taxId = $document->extracted_data['supplier_tax_id'] ?? null;
        $supplier = $taxId ? Supplier::whereRaw('upper(tax_id) = ?', [mb_strtoupper($taxId)])->first() : null;
        $classification = (float) ($document->extracted_data['total'] ?? 0) < 0 ? 'supplier_credit_note' : 'supplier_invoice';
        $document->update([
            'supplier_id' => $supplier?->id,
            'classification' => $classification,
            'status' => $supplier ? 'ready' : 'pending_review',
        ]);

        return true;
    }
}
