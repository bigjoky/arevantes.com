<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplenishmentPolicy extends Model
{
    protected $fillable = ['warehouse_id', 'product_id', 'reorder_point', 'target_stock', 'is_active'];

    protected function casts(): array
    {
        return ['reorder_point' => 'decimal:3', 'target_stock' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
