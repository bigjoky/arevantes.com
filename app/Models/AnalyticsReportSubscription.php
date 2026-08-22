<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsReportSubscription extends Model
{
    protected $fillable = ['user_id', 'area', 'frequency', 'format', 'filters', 'timezone', 'enabled', 'last_sent_at', 'next_send_at'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'enabled' => 'boolean', 'last_sent_at' => 'datetime', 'next_send_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
