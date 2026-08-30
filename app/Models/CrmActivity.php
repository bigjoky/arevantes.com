<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmActivity extends Model
{
    use SoftDeletes;

    protected $touches = ['workOrder'];

    protected $fillable = ['planning_scenario_request_id', 'installation_id', 'work_order_id', 'person_id', 'opportunity_id', 'assigned_user_id', 'created_by', 'type', 'title', 'notes', 'priority', 'status', 'blocked_reason', 'starts_at', 'due_at', 'completed_at', 'completed_by', 'completion_notes'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'due_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class)->withTrashed();
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class)->withTrashed();
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
