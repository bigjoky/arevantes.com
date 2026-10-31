<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalVehicleAssignmentHistory extends Model
{
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(CrmActivity::class, 'crm_activity_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function previousAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'old_assignee_id');
    }

    public function nextAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_assignee_id');
    }

    public function previousVehicle(): BelongsTo
    {
        return $this->belongsTo(OperationalVehicle::class, 'old_vehicle_id');
    }

    public function nextVehicle(): BelongsTo
    {
        return $this->belongsTo(OperationalVehicle::class, 'new_vehicle_id');
    }

    protected function casts(): array
    {
        return ['old_start_utc' => 'immutable_datetime', 'old_end_utc' => 'immutable_datetime', 'new_start_utc' => 'immutable_datetime', 'new_end_utc' => 'immutable_datetime'];
    }

    protected $table = 'operational_vehicle_assignment_history';

    protected $guarded = ['id'];
}
