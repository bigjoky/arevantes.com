<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteLine extends Model
{
    protected $fillable = ['product_id', 'unit_id', 'tax_rate_id', 'position', 'selection_mode', 'option_group', 'is_selected', 'description', 'quantity', 'unit_price', 'discount_percent', 'tax_percent', 'subtotal', 'discount_amount', 'tax_amount', 'total'];

    protected function casts(): array
    {
        return ['is_selected' => 'boolean', 'quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'discount_percent' => 'decimal:2', 'tax_percent' => 'decimal:2', 'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(QuoteLineComponent::class)->orderBy('position');
    }
}
