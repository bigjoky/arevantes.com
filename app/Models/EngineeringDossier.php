<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngineeringDossier extends Model
{
    protected $fillable = [
        'opportunity_id', 'installation_id', 'code', 'title', 'business_line', 'kind', 'status', 'current_version',
        'scope', 'site_conditions', 'calculations', 'proposed_solution', 'risks', 'standards', 'conclusions',
        'created_by', 'updated_by', 'approved_by', 'approved_at', 'handed_over_by', 'handed_over_at',
    ];

    protected function casts(): array
    {
        return [
            'current_version' => 'integer',
            'approved_at' => 'datetime',
            'handed_over_at' => 'datetime',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class)->withTrashed();
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function handoverActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EngineeringDossierVersion::class)->latest('version');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EngineeringDossierEvent::class)->latest('occurred_at');
    }
}
