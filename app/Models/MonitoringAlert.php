<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringAlert extends Model
{
    protected $fillable = ['installation_id', 'provider', 'status', 'message', 'occurrences', 'started_at', 'last_seen_at', 'notified_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'last_seen_at' => 'datetime', 'notified_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }
}
