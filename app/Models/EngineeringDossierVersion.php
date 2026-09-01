<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineeringDossierVersion extends Model
{
    protected $fillable = ['engineering_dossier_id', 'version', 'snapshot', 'fingerprint', 'change_note', 'created_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'snapshot' => 'array'];
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(EngineeringDossier::class, 'engineering_dossier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
