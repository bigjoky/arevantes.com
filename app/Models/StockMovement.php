<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMovement extends Model
{
    protected $fillable = ['created_by', 'sales_document_id', 'purchase_document_id', 'installation_id', 'work_order_id', 'number', 'type', 'reference', 'notes', 'posted_at'];

    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
