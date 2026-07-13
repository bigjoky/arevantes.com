<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenancePlan extends Model
{
    protected $fillable = ['installation_id', 'name', 'interval_months', 'starts_on', 'next_due_on', 'last_completed_on', 'is_active', 'scope'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'next_due_on' => 'date', 'last_completed_on' => 'date', 'is_active' => 'boolean'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class);
    }
}
