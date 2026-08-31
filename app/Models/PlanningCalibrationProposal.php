<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanningCalibrationProposal extends Model
{
    protected $fillable = [
        'business_line', 'area', 'metric_label', 'metric_key', 'unit', 'version', 'status', 'direction',
        'sample_size', 'evidence_snapshot', 'source_fingerprint', 'recommendation', 'created_by',
        'assumption_key', 'accepted_value', 'decided_by', 'decided_at', 'decision_note',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(PlanningCalibrationEvaluation::class)->latest('version');
    }

    protected function casts(): array
    {
        return [
            'evidence_snapshot' => 'array',
            'accepted_value' => 'float',
            'decided_at' => 'datetime',
        ];
    }
}
