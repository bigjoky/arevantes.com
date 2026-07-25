<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProductPrice extends Model
{
    protected $fillable = ['supplier_product_id', 'purchase_document_line_id', 'unit_price', 'currency', 'recorded_at'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'recorded_at' => 'datetime'];
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function purchaseDocumentLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseDocumentLine::class);
    }
}
