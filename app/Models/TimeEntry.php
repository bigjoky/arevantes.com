<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    protected $appends = ['cost'];

    protected $fillable = ['client_uuid', 'work_order_id', 'user_id', 'created_by', 'started_at', 'ended_at', 'duration_minutes', 'hourly_cost', 'travel_km', 'mileage_rate', 'notes'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'hourly_cost' => 'decimal:2', 'travel_km' => 'decimal:2', 'mileage_rate' => 'decimal:3'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getCostAttribute(): float
    {
        return round((($this->duration_minutes ?? 0) / 60) * (float) $this->hourly_cost + (float) $this->travel_km * (float) $this->mileage_rate, 2);
    }
}
