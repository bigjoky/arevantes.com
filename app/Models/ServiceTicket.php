<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceTicket extends Model
{
    protected $fillable = ['installation_id', 'maintenance_plan_id', 'maintenance_plan_version', 'assigned_user_id', 'created_by', 'work_order_id', 'sla_policy_id', 'number', 'kind', 'priority', 'status', 'title', 'description', 'resolution', 'first_response_due_at', 'resolution_due_at', 'first_responded_at', 'escalated_at', 'escalation_level', 'reopened_count', 'satisfaction_rating', 'resolved_at'];

    protected function casts(): array
    {
        return ['first_response_due_at' => 'datetime', 'resolution_due_at' => 'datetime', 'first_responded_at' => 'datetime', 'escalated_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(ServiceSlaPolicy::class, 'sla_policy_id');
    }

    public function serviceCycleCommitment(): HasOne
    {
        return $this->hasOne(ServiceCycleCommitment::class);
    }
}
