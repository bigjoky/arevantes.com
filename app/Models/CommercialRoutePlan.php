<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialRoutePlan extends Model
{
    protected $fillable = [
        'user_id',
        'route_date',
        'status',
        'origin_latitude',
        'origin_longitude',
        'optimized_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'route_date' => 'date',
            'origin_latitude' => 'float',
            'origin_longitude' => 'float',
            'optimized_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(CommercialRouteStop::class)->orderBy('position');
    }
}
