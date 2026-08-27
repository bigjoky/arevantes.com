<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCycleScheduleRevision extends Model
{
    protected $fillable = ['service_cycle_commitment_id', 'work_order_id', 'version', 'status', 'scheduled_start', 'scheduled_end', 'assigned_user_id', 'reason', 'created_by', 'confirmed_by', 'confirmed_at', 'superseded_at'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'scheduled_start' => 'datetime', 'scheduled_end' => 'datetime', 'confirmed_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    public function commitment(): BelongsTo
    {
        return $this->belongsTo(ServiceCycleCommitment::class, 'service_cycle_commitment_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
