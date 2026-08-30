<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanningScenarioReview extends Model
{
    protected $fillable = ['planning_scenario_id', 'sequence', 'outcome_snapshot', 'fingerprint', 'explanation', 'created_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['outcome_snapshot' => 'array', 'sequence' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(PlanningScenario::class, 'planning_scenario_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
