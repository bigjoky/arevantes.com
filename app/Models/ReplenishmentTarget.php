<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplenishmentTarget extends Model
{
    protected $fillable = ['product_id', 'period_starts_on', 'period_ends_on', 'status', 'target_quantity', 'expected_quantity', 'minimum_stock', 'free_stock', 'inbound_quantity', 'forecast_shortage', 'source_snapshot', 'approval_note', 'approved_by', 'approved_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'];

    protected function casts(): array
    {
        return [
            'period_starts_on' => 'date', 'period_ends_on' => 'date', 'target_quantity' => 'decimal:3',
            'expected_quantity' => 'decimal:3', 'minimum_stock' => 'decimal:3', 'free_stock' => 'decimal:3',
            'inbound_quantity' => 'decimal:3', 'forecast_shortage' => 'decimal:3', 'source_snapshot' => 'array',
            'approved_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
