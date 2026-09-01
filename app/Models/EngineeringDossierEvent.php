<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineeringDossierEvent extends Model
{
    protected $fillable = ['engineering_dossier_id', 'actor_id', 'type', 'from_status', 'to_status', 'dossier_version', 'note', 'occurred_at'];

    protected function casts(): array
    {
        return ['dossier_version' => 'integer', 'occurred_at' => 'datetime'];
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(EngineeringDossier::class, 'engineering_dossier_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
