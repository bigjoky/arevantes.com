<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanningCalibrationGovernanceReview extends Model
{
    protected $fillable = [
        'planning_calibration_proposal_id', 'version', 'status', 'recommendation', 'sample_size',
        'retain_count', 'revise_count', 'retire_count', 'consistency', 'evidence_snapshot',
        'fingerprint', 'created_by', 'decision', 'replacement_proposal_id', 'decided_by',
        'decided_at', 'decision_note',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(PlanningCalibrationProposal::class, 'planning_calibration_proposal_id');
    }

    public function replacementProposal(): BelongsTo
    {
        return $this->belongsTo(PlanningCalibrationProposal::class, 'replacement_proposal_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    protected function casts(): array
    {
        return [
            'consistency' => 'float',
            'evidence_snapshot' => 'array',
            'decided_at' => 'datetime',
        ];
    }
}
