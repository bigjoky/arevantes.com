<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    protected $fillable = ['installation_id', 'work_order_id', 'person_id', 'assigned_user_id', 'created_by', 'type', 'title', 'notes', 'priority', 'status', 'starts_at', 'due_at', 'completed_at'];

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
        return $this->belongsTo(Person::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
