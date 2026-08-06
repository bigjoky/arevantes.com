<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceSlaPolicy extends Model
{
    protected $fillable = ['name', 'priority', 'customer_type', 'first_response_minutes', 'resolution_minutes', 'escalation_minutes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class, 'sla_policy_id');
    }
}
