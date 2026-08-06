<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPortalAccess extends Model
{
    protected $fillable = ['person_id', 'created_by', 'recipient_email', 'token_hash', 'expires_at', 'sent_at', 'first_accessed_at', 'last_accessed_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'sent_at' => 'datetime', 'first_accessed_at' => 'datetime', 'last_accessed_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id')->withTrashed();
    }
}
