<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanningScenarioRequest extends Model
{
    protected $fillable = [
        'planning_scenario_id', 'area', 'status', 'approved_fingerprint', 'request_fingerprint',
        'payload', 'generated_by', 'confirmed_by', 'confirmed_at', 'confirmation_note',
        'materialization_status', 'materialization_preview', 'materialization_fingerprint', 'idempotency_key',
        'prepared_by', 'prepared_at', 'authorized_by', 'authorized_at', 'authorization_note',
        'execution_status', 'execution_result', 'execution_fingerprint', 'executed_by', 'executed_at',
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

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'confirmed_at' => 'datetime',
            'materialization_preview' => 'array',
            'prepared_at' => 'datetime',
            'authorized_at' => 'datetime',
            'execution_result' => 'array',
            'executed_at' => 'datetime',
        ];
    }
}
