<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsAlert extends Model
{
    protected $fillable = ['user_id', 'metric_key', 'operator', 'threshold', 'enabled', 'last_triggered_at'];

    protected function casts(): array
    {
        return ['threshold' => 'decimal:2', 'enabled' => 'boolean', 'last_triggered_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
