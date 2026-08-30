<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanningScenario extends Model
{
    protected $fillable = [
        'name', 'version', 'status', 'scenario_kind', 'baseline_scenario_id', 'horizon_starts_on', 'horizon_ends_on',
        'assumptions', 'opportunity_snapshot', 'result_snapshot', 'fingerprint',
        'reference_snapshot', 'reference_valid_until', 'comparison_snapshot', 'created_by', 'approved_by', 'approved_at',
        'approved_fingerprint', 'approval_note',
        'closure_snapshot', 'closure_fingerprint', 'closure_note', 'closed_by', 'closed_at',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function baselineScenario(): BelongsTo
    {
        return $this->belongsTo(self::class, 'baseline_scenario_id');
    }

    public function actionRequests(): HasMany
    {
        return $this->hasMany(PlanningScenarioRequest::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PlanningScenarioReview::class)->orderBy('sequence');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    protected function casts(): array
    {
        return [
            'horizon_starts_on' => 'date:Y-m-d',
            'horizon_ends_on' => 'date:Y-m-d',
            'assumptions' => 'array',
            'opportunity_snapshot' => 'array',
            'result_snapshot' => 'array',
            'reference_snapshot' => 'array',
            'reference_valid_until' => 'datetime',
            'comparison_snapshot' => 'array',
            'approved_at' => 'datetime',
            'closure_snapshot' => 'array',
            'closed_at' => 'datetime',
        ];
    }
}
