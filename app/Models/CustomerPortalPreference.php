<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPortalPreference extends Model
{
    protected $fillable = ['person_id', 'email_documents', 'email_tickets', 'email_maintenance'];

    protected function casts(): array
    {
        return ['email_documents' => 'boolean', 'email_tickets' => 'boolean', 'email_maintenance' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id')->withTrashed();
    }
}
