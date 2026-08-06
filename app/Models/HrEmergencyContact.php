<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmergencyContact extends Model
{
    protected $fillable = [
        'person_id',
        'name',
        'relationship',
        'phone',
        'prevention_notes',
        'is_primary',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'prevention_notes' => 'encrypted',
            'is_primary' => 'boolean',
        ];
    }
}
