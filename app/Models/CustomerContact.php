<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerContact extends Model
{
    protected $fillable = ['person_id', 'first_name', 'last_name', 'position', 'roles', 'document_type', 'document_number', 'email', 'phone', 'preferred_channel', 'is_primary', 'is_authorized_signatory', 'notes'];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'is_primary' => 'boolean',
            'is_authorized_signatory' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id')->withTrashed();
    }
}
