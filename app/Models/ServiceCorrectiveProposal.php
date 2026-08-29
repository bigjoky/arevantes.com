<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCorrectiveProposal extends Model
{
    protected $fillable = [
        'service_corrective_plan_id', 'type', 'title', 'rationale', 'payload',
        'target_type', 'target_id', 'target_version', 'target_snapshot', 'status',
        'decision_note', 'decided_by', 'decided_at', 'resubmission_count', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array', 'target_snapshot' => 'array',
            'decided_at' => 'datetime', 'resubmission_count' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ServiceCorrectivePlan::class, 'service_corrective_plan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
