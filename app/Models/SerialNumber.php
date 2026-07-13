<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
