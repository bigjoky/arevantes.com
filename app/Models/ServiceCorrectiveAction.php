<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCorrectiveAction extends Model
{
    protected $fillable = ['service_corrective_plan_id', 'type', 'title', 'details', 'status', 'owner_id', 'due_on', 'completion_note', 'completed_by', 'completed_at'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'completed_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ServiceCorrectivePlan::class, 'service_corrective_plan_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
