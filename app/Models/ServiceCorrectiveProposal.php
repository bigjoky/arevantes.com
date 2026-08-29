<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCorrectiveProposal extends Model
{
    protected $fillable = [
        'service_corrective_plan_id', 'type', 'title', 'rationale', 'payload',
        'target_type', 'target_id', 'target_version', 'target_snapshot', 'status',
        'decision_note', 'decided_by', 'decided_at', 'resubmission_count', 'created_by',
        'application_note', 'application_result', 'applied_by', 'applied_at',
        'superseded_by_proposal_id', 'supersession_note', 'superseded_by', 'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array', 'target_snapshot' => 'array',
            'decided_at' => 'datetime', 'resubmission_count' => 'integer',
            'application_result' => 'array', 'applied_at' => 'datetime', 'superseded_at' => 'datetime',
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

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(ServiceCorrectiveProposalApplication::class, 'service_corrective_proposal_id')->latest();
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_proposal_id');
    }

    public function superseder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superseded_by');
    }
}
