<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCycleDeviationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['service_cycle_deviation_id', 'event', 'from_status', 'to_status', 'note', 'changes', 'actor_id', 'created_at'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function deviation(): BelongsTo
    {
        return $this->belongsTo(ServiceCycleDeviation::class, 'service_cycle_deviation_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
