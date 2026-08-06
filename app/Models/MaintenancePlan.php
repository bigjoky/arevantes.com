<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenancePlan extends Model
{
    protected $fillable = ['installation_id', 'work_order_template_id', 'assigned_user_id', 'name', 'interval_months', 'lead_days', 'starts_on', 'next_due_on', 'last_completed_on', 'is_active', 'generate_work_order', 'last_generated_at', 'scope'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'next_due_on' => 'date', 'last_completed_on' => 'date', 'is_active' => 'boolean', 'generate_work_order' => 'boolean', 'last_generated_at' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkOrderTemplate::class, 'work_order_template_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
