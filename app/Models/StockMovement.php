<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMovement extends Model
{
    protected $fillable = ['operation_key', 'operation_hash', 'material_condition', 'source_movement_id', 'recipient_id', 'created_by', 'sales_document_id', 'purchase_document_id', 'installation_id', 'work_order_id', 'number', 'type', 'reference', 'notes', 'posted_at'];

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

    public function purchaseDocument(): BelongsTo
    {
        return $this->belongsTo(PurchaseDocument::class);
    }

    public function salesDocument(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class);
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function sourceMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_movement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
