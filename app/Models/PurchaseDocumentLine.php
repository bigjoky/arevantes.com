<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseDocumentLine extends Model
{
    protected $fillable = ['source_line_id', 'product_id', 'position', 'description', 'quantity', 'received_quantity', 'returned_quantity', 'unit_price', 'estimated_unit_price', 'discount_percent', 'tax_percent', 'subtotal', 'tax_amount', 'total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'received_quantity' => 'decimal:3', 'returned_quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'estimated_unit_price' => 'decimal:2', 'discount_percent' => 'decimal:2', 'tax_percent' => 'decimal:2', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PurchaseDocument::class, 'purchase_document_id');
    }

    public function sourceLine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_line_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
