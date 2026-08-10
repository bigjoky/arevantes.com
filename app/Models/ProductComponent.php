<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductComponent extends Model
{
    protected $fillable = ['component_product_id', 'position', 'quantity', 'fulfillment_kind', 'visibility', 'interval_months'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'interval_months' => 'integer'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
