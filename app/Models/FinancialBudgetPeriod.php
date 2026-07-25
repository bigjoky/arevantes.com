<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialBudgetPeriod extends Model
{
    protected $fillable = [
        'year',
        'status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'reopened_by',
        'reopened_at',
        'reopen_reason',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'approved_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }
}
