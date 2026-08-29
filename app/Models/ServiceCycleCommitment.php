<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceCycleCommitment extends Model
{
    protected $hidden = ['technical_snapshot', 'completion_snapshot'];

    protected $fillable = ['maintenance_plan_id', 'maintenance_plan_version', 'period_starts_on', 'period_ends_on', 'status', 'planned_visits', 'planned_minutes', 'planned_travel_km', 'planned_revenue', 'planned_labor_cost', 'planned_material_cost', 'planned_travel_cost', 'capacity_snapshot', 'technical_snapshot', 'service_ticket_id', 'work_order_id', 'schedule_status', 'materialized_by', 'materialized_at', 'schedule_confirmed_by', 'schedule_confirmed_at', 'completion_snapshot', 'completed_by', 'completed_at', 'approval_note', 'approved_by', 'approved_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'];

    protected function casts(): array
    {
        return [
            'maintenance_plan_version' => 'integer', 'period_starts_on' => 'date', 'period_ends_on' => 'date',
            'planned_visits' => 'integer', 'planned_minutes' => 'integer', 'planned_travel_km' => 'decimal:2',
            'planned_revenue' => 'decimal:2', 'planned_labor_cost' => 'decimal:2', 'planned_material_cost' => 'decimal:2',
            'planned_travel_cost' => 'decimal:2', 'capacity_snapshot' => 'array', 'technical_snapshot' => 'array', 'completion_snapshot' => 'array', 'approved_at' => 'datetime', 'materialized_at' => 'datetime', 'schedule_confirmed_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
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

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function billing(): HasOne
    {
        return $this->hasOne(RecurringServiceBilling::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(ServiceCycleCommunication::class)->orderByDesc('scheduled_for');
    }

    public function serviceTicket(): BelongsTo
    {
        return $this->belongsTo(ServiceTicket::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function scheduleRevisions(): HasMany
    {
        return $this->hasMany(ServiceCycleScheduleRevision::class)->orderByDesc('version');
    }

    public function deviations(): HasMany
    {
        return $this->hasMany(ServiceCycleDeviation::class);
    }
}
