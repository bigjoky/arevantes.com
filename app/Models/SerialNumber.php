<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SerialNumber extends Model
{
    protected $fillable = ['product_id', 'warehouse_id', 'installation_id', 'received_line_id', 'issued_line_id', 'serial_number', 'status', 'received_at', 'issued_at', 'warranty_starts_on', 'warranty_ends_on'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'issued_at' => 'datetime', 'warranty_starts_on' => 'date', 'warranty_ends_on' => 'date'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function receivedLine(): BelongsTo
    {
        return $this->belongsTo(StockMovementLine::class, 'received_line_id');
    }

    public function issuedLine(): BelongsTo
    {
        return $this->belongsTo(StockMovementLine::class, 'issued_line_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SerialNumberEvent::class)->orderBy('occurred_at')->orderBy('id');
    }
}
