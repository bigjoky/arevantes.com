<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCycleCommitment extends Model
{
    protected $fillable = ['maintenance_plan_id', 'maintenance_plan_version', 'period_starts_on', 'period_ends_on', 'status', 'planned_visits', 'planned_minutes', 'planned_travel_km', 'planned_revenue', 'planned_labor_cost', 'planned_material_cost', 'planned_travel_cost', 'capacity_snapshot', 'approval_note', 'approved_by', 'approved_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'];

    protected function casts(): array
    {
        return [
            'maintenance_plan_version' => 'integer', 'period_starts_on' => 'date', 'period_ends_on' => 'date',
            'planned_visits' => 'integer', 'planned_minutes' => 'integer', 'planned_travel_km' => 'decimal:2',
            'planned_revenue' => 'decimal:2', 'planned_labor_cost' => 'decimal:2', 'planned_material_cost' => 'decimal:2',
            'planned_travel_cost' => 'decimal:2', 'capacity_snapshot' => 'array', 'approved_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
