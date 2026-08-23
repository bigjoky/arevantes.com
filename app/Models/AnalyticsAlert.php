<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsAlert extends Model
{
    protected $fillable = ['user_id', 'metric_key', 'area', 'filters', 'operator', 'threshold', 'channel', 'enabled', 'last_value', 'is_triggered', 'last_evaluated_at', 'last_triggered_at'];

    protected function casts(): array
    {
        return [
            'filters' => 'array', 'threshold' => 'decimal:2', 'enabled' => 'boolean',
            'last_value' => 'decimal:2', 'is_triggered' => 'boolean',
            'last_evaluated_at' => 'datetime', 'last_triggered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
