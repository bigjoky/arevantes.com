<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileDevice extends Model
{
    protected $fillable = ['user_id', 'device_id', 'platform', 'push_token', 'environment', 'app_version', 'enabled', 'last_seen_at'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'last_seen_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
