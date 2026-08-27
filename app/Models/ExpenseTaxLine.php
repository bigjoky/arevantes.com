<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseTaxLine extends Model
{
    protected $fillable = [
        'position', 'kind', 'taxable_base', 'tax_rate', 'tax_amount',
        'deductible_percent', 'deductible_amount', 'exemption_reason',
    ];

    protected function casts(): array
    {
        return [
            'taxable_base' => 'decimal:2', 'tax_rate' => 'decimal:3',
            'tax_amount' => 'decimal:2', 'deductible_percent' => 'decimal:2',
            'deductible_amount' => 'decimal:2',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
