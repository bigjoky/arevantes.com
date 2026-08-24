<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteVersion extends Model
{
    protected $fillable = ['quote_id', 'version', 'created_by', 'service_type', 'subtotal', 'discount_amount', 'tax_amount', 'amount', 'expected_margin_amount', 'lines', 'notes'];

    protected function casts(): array
    {
        return ['lines' => 'array', 'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'amount' => 'decimal:2', 'expected_margin_amount' => 'decimal:2'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
