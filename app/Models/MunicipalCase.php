<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MunicipalCase extends Model
{
    protected $fillable = [
        'reference', 'installation_id', 'municipality_id', 'client_person_id',
        'procedure_code', 'procedure_version', 'administrative_status',
        'execution_status', 'representation_status', 'representation_expires_on',
        'input_snapshot', 'urban_planning_checks', 'pem_amount', 'pem_currency',
        'submitted_at', 'execution_authorized_at', 'execution_authorized_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'input_snapshot' => 'array',
            'urban_planning_checks' => 'array',
            'pem_amount' => 'decimal:2',
            'representation_expires_on' => 'date',
            'submitted_at' => 'datetime',
            'execution_authorized_at' => 'datetime',
        ];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'client_person_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MunicipalCaseDocument::class);
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(MunicipalCaseTaxObligation::class);
    }
}
