<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    protected $fillable = ['user_id', 'type', 'severity', 'correlation_id', 'ip_address', 'context', 'acknowledged_at'];

    protected function casts(): array
    {
        return ['context' => 'array', 'acknowledged_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
