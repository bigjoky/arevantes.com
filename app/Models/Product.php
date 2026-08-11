<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['type', 'sku', 'name', 'description', 'unit_id', 'tax_rate_id', 'sale_account_code', 'purchase_account_code', 'sale_price', 'cost_price', 'track_stock', 'track_serials', 'is_active'];

    protected function casts(): array
    {
        return ['sale_price' => 'decimal:2', 'cost_price' => 'decimal:2', 'track_stock' => 'boolean', 'track_serials' => 'boolean', 'is_active' => 'boolean'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(ProductComponent::class, 'parent_product_id')->orderBy('position');
    }
}
