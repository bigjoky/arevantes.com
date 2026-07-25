<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialForecastItem extends Model
{
    protected $fillable = ['direction', 'category', 'description', 'amount', 'due_on', 'probability', 'recurrence', 'status', 'installation_id', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_on' => 'date', 'probability' => 'integer'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }
}
