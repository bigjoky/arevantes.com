<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebVitalSample extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'route_name', 'metric', 'rating', 'value', 'delta', 'device', 'navigation_type', 'release', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'delta' => 'float',
            'occurred_at' => 'datetime',
        ];
    }
}
