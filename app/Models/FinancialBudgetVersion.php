<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialBudgetVersion extends Model
{
    protected $fillable = [
        'financial_budget_period_id',
        'revision',
        'approved_by',
        'approved_at',
        'approval_notes',
        'values',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'approved_at' => 'datetime',
            'values' => 'array',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialBudgetPeriod::class, 'financial_budget_period_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
