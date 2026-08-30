<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanningScenarioRequest extends Model
{
    protected $fillable = [
        'planning_scenario_id', 'area', 'status', 'approved_fingerprint', 'request_fingerprint',
        'payload', 'generated_by', 'confirmed_by', 'confirmed_at', 'confirmation_note',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(PlanningScenario::class, 'planning_scenario_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }
}
