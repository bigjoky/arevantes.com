<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialBudget extends Model
{
    protected $fillable = ['year', 'month', 'category', 'financial_category_id', 'amount', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'amount' => 'decimal:2'];
    }

    public function financialCategory(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class);
    }
}
