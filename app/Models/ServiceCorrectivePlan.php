<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCorrectivePlan extends Model
{
    protected $fillable = [
        'code', 'maintenance_plan_id', 'title', 'root_cause_category', 'root_cause_analysis',
        'status', 'owner_id', 'target_date', 'verification_cycles', 'baseline_snapshot',
        'effectiveness_snapshot', 'effectiveness_result', 'effectiveness_note', 'started_at',
        'verification_started_at', 'verified_by', 'verified_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date', 'verification_cycles' => 'integer',
            'baseline_snapshot' => 'array', 'effectiveness_snapshot' => 'array',
            'started_at' => 'datetime', 'verification_started_at' => 'datetime', 'verified_at' => 'datetime',
        ];
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function deviations(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCycleDeviation::class, 'service_corrective_plan_deviation');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ServiceCorrectiveAction::class)->orderBy('due_on');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(ServiceCorrectiveProposal::class)->latest();
    }

    public function events(): HasMany
    {
        return $this->hasMany(ServiceCorrectivePlanEvent::class)->latest('created_at');
    }
}
