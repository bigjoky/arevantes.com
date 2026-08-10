<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteLineComponent extends Model
{
    protected $fillable = ['product_id', 'unit_id', 'position', 'kind', 'fulfillment_kind', 'visibility', 'description', 'quantity', 'planned_unit_cost', 'interval_months'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'planned_unit_cost' => 'decimal:2', 'interval_months' => 'integer'];
    }

    public function quoteLine(): BelongsTo
    {
        return $this->belongsTo(QuoteLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
