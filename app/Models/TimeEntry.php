<?php

namespace App\Models;

use App\Casts\PreciseInstant;
use App\Models\Concerns\HasPreciseInstants;
use App\Support\Time\BusinessTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class TimeEntry extends Model
{
    use HasPreciseInstants;

    protected $touches = ['workOrder'];

    protected $appends = ['cost'];

    protected $fillable = ['client_uuid', 'work_order_id', 'user_id', 'created_by', 'started_at', 'ended_at', 'duration_minutes', 'hourly_cost', 'travel_km', 'mileage_rate', 'notes'];

    protected function casts(): array
    {
        return ['started_at' => PreciseInstant::class, 'ended_at' => PreciseInstant::class, 'hourly_cost' => 'decimal:2', 'travel_km' => 'decimal:2', 'mileage_rate' => 'decimal:3'];
    }

    public function assertUnambiguousStart(): void
    {
        if (! $this->getRawOriginal('started_at_utc') && count(BusinessTime::candidates((string) $this->getRawOriginal('started_at'))) !== 1) {
            throw ValidationException::withMessages(['started_at' => 'El inicio histórico no identifica un instante único. Revisa la hora con su desplazamiento antes de calcular la duración.']);
        }
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
