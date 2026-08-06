<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsReportSubscription extends Model
{
    protected $fillable = ['user_id', 'area', 'frequency', 'format', 'enabled', 'last_sent_at'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'last_sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
