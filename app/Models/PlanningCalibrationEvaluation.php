<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanningCalibrationEvaluation extends Model
{
    protected $fillable = [
        'planning_calibration_proposal_id', 'planning_scenario_id', 'version', 'status', 'recommendation',
        'origin_delta_percent', 'observed_delta_percent', 'improvement_points', 'evidence_snapshot',
        'fingerprint', 'created_by', 'decision', 'decided_by', 'decided_at', 'decision_note',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(PlanningCalibrationProposal::class, 'planning_calibration_proposal_id');
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(PlanningScenario::class, 'planning_scenario_id');
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
            'origin_delta_percent' => 'float',
            'observed_delta_percent' => 'float',
            'improvement_points' => 'float',
            'evidence_snapshot' => 'array',
            'decided_at' => 'datetime',
        ];
    }
}
