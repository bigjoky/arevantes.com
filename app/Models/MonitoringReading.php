<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringReading extends Model
{
    protected $fillable = ['installation_id', 'provider', 'status', 'current_power_kw', 'today_energy_kwh', 'status_message', 'provider_data', 'recorded_at'];

    protected function casts(): array
    {
        return ['provider_data' => 'array', 'recorded_at' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }
}
