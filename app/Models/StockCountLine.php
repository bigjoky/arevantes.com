<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends Model
{
    protected $fillable = ['product_id', 'system_quantity', 'counted_quantity', 'difference_quantity', 'unit_cost', 'discrepancy_count', 'snapshot_serials', 'counted_serials', 'missing_serials', 'unexpected_serials'];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'difference_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'discrepancy_count' => 'integer',
            'snapshot_serials' => 'array',
            'counted_serials' => 'array',
            'missing_serials' => 'array',
            'unexpected_serials' => 'array',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
