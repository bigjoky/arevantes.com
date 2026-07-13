<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationMaterialRequirement extends Model
{
    protected $fillable = [
        'installation_id',
        'quote_id',
        'product_id',
        'source',
        'sku',
        'description',
        'planned_quantity',
        'planned_unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:3',
            'planned_unit_cost' => 'decimal:2',
        ];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
