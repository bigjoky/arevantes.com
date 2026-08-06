<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierQualityIncident extends Model
{
    protected $fillable = ['purchase_document_id', 'goods_receipt_id', 'product_id', 'warehouse_id', 'reported_by', 'rejected_quantity', 'reason', 'severity', 'notes', 'status', 'resolution', 'resolution_notes', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['rejected_quantity' => 'decimal:3', 'resolved_at' => 'datetime'];
    }

    public function purchaseDocument(): BelongsTo
    {
        return $this->belongsTo(PurchaseDocument::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
