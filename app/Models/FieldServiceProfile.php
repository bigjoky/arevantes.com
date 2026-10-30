<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldServiceProfile extends Model
{
    protected $fillable = ['weekly_schedule', 'user_id', 'daily_capacity_minutes', 'max_travel_km', 'home_postal_code', 'service_zones', 'skills', 'is_available'];

    protected function casts(): array
    {
        return ['weekly_schedule' => 'array', 'service_zones' => 'array', 'skills' => 'array', 'is_available' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
