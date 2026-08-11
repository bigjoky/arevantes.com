<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseSetting extends Model
{
    protected $fillable = ['matching_amount_tolerance', 'matching_percent_tolerance', 'shipping_tax_percent', 'order_approval_threshold'];

    protected function casts(): array
    {
        return [
            'matching_amount_tolerance' => 'decimal:2',
            'matching_percent_tolerance' => 'decimal:2',
            'shipping_tax_percent' => 'decimal:2',
            'order_approval_threshold' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
