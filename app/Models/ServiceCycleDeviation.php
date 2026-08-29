<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCycleDeviation extends Model
{
    protected $fillable = [
        'service_cycle_commitment_id', 'business_line', 'category', 'severity', 'status',
        'planned_value', 'actual_value', 'variance_percent', 'tolerance_percent', 'unit',
        'summary', 'detection_context', 'assigned_to', 'review_due_on', 'detected_at',
        'confirmed_by', 'confirmed_at', 'dismissed_by', 'dismissed_at',
        'resolved_by', 'resolved_at', 'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'planned_value' => 'decimal:3', 'actual_value' => 'decimal:3',
            'variance_percent' => 'decimal:2', 'tolerance_percent' => 'decimal:2',
            'detection_context' => 'array', 'review_due_on' => 'date', 'detected_at' => 'datetime',
            'confirmed_at' => 'datetime', 'dismissed_at' => 'datetime', 'resolved_at' => 'datetime',
        ];
    }

    public function commitment(): BelongsTo
    {
        return $this->belongsTo(ServiceCycleCommitment::class, 'service_cycle_commitment_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function dismisser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ServiceCycleDeviationEvent::class)->latest('created_at');
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(InstallationAsset::class, 'service_cycle_deviation_assets');
    }
}
