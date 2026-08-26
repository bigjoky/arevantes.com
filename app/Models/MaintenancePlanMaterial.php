<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePlanMaterial extends Model
{
    protected $fillable = ['maintenance_plan_id', 'product_id', 'quantity_per_visit', 'expected_usage_percent', 'minimum_stock', 'notes'];

    protected function casts(): array
    {
        return [
            'quantity_per_visit' => 'decimal:3',
            'expected_usage_percent' => 'decimal:2',
            'minimum_stock' => 'decimal:3',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
