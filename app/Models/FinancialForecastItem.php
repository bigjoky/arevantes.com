<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialForecastItem extends Model
{
    protected $fillable = ['direction', 'category', 'description', 'amount', 'baseline_amount', 'due_on', 'baseline_due_on', 'probability', 'recurrence', 'status', 'actual_amount', 'settled_on', 'settlement_notes', 'installation_id', 'created_by', 'settled_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'baseline_amount' => 'decimal:2',
            'due_on' => 'date',
            'baseline_due_on' => 'date',
            'probability' => 'integer',
            'actual_amount' => 'decimal:2',
            'settled_on' => 'date',
        ];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}
