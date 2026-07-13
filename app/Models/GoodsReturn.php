<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReturn extends Model
{
    protected $fillable = ['purchase_document_id', 'stock_movement_id', 'warehouse_id', 'created_by', 'number', 'supplier_return_number', 'returned_at'];

    protected function casts(): array
    {
        return ['returned_at' => 'datetime'];
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }
}
