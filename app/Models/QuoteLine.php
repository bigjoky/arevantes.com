<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteLine extends Model
{
    protected $fillable = ['parent_line_id', 'depth', 'product_id', 'unit_id', 'tax_rate_id', 'position', 'selection_mode', 'option_group', 'is_selected', 'description', 'quantity', 'unit_price', 'total_with_tax', 'discount_percent', 'tax_percent', 'subtotal', 'discount_amount', 'tax_amount', 'total'];

    protected function casts(): array
    {
        return ['is_selected' => 'boolean', 'quantity' => 'decimal:3', 'unit_price' => 'decimal:3', 'total_with_tax' => 'decimal:2', 'discount_percent' => 'decimal:2', 'tax_percent' => 'decimal:2', 'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_line_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_line_id')->orderBy('position');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(QuoteLineComponent::class)->orderBy('position');
    }

    public function effectiveComponentQuantity(QuoteLineComponent $component): float
    {
        $quantity = (float) $this->quantity * (float) $component->quantity;
        $components = $this->components->keyBy('id');
        $parentId = $component->parent_component_id;

        while ($parentId) {
            $parent = $components->get($parentId);
            if (! $parent) {
                break;
            }

            $quantity *= (float) $parent->quantity;
            $parentId = $parent->parent_component_id;
        }

        return $quantity;
    }
}
