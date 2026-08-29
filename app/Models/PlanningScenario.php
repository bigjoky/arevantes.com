<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanningScenario extends Model
{
    protected $fillable = [
        'name', 'version', 'status', 'horizon_starts_on', 'horizon_ends_on',
        'assumptions', 'opportunity_snapshot', 'result_snapshot', 'fingerprint',
        'created_by', 'approved_by', 'approved_at',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function casts(): array
    {
        return [
            'horizon_starts_on' => 'date:Y-m-d',
            'horizon_ends_on' => 'date:Y-m-d',
            'assumptions' => 'array',
            'opportunity_snapshot' => 'array',
            'result_snapshot' => 'array',
            'approved_at' => 'datetime',
        ];
    }
}
