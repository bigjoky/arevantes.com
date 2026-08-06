<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierProduct extends Model
{
    protected $fillable = ['supplier_id', 'product_id', 'supplier_sku', 'supplier_description', 'minimum_order_quantity', 'order_multiple', 'lead_time_days', 'last_unit_price', 'currency', 'is_preferred', 'is_active'];

    protected function casts(): array
    {
        return [
            'minimum_order_quantity' => 'decimal:3',
            'order_multiple' => 'decimal:3',
            'last_unit_price' => 'decimal:2',
            'is_preferred' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(SupplierProductPrice::class)->latest('recorded_at');
    }
}
