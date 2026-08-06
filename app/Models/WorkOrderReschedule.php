<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderReschedule extends Model
{
    protected $fillable = ['work_order_id', 'changed_by', 'old_assigned_user_id', 'new_assigned_user_id', 'old_scheduled_start', 'old_scheduled_end', 'new_scheduled_start', 'new_scheduled_end', 'batch_uuid', 'reason'];

    protected function casts(): array
    {
        return ['old_scheduled_start' => 'datetime', 'old_scheduled_end' => 'datetime', 'new_scheduled_start' => 'datetime', 'new_scheduled_end' => 'datetime'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
