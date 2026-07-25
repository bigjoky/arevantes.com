<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialBudget extends Model
{
    protected $fillable = ['year', 'month', 'category', 'amount', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'amount' => 'decimal:2'];
    }
}
