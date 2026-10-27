<?php

namespace App\Models;

use App\Casts\PreciseInstant;
use App\Models\Concerns\HasPreciseInstants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPortalAccess extends Model
{
    use HasPreciseInstants;

    protected $fillable = ['person_id', 'created_by', 'recipient_email', 'token_hash', 'expires_at', 'sent_at', 'first_accessed_at', 'last_accessed_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['expires_at' => PreciseInstant::class, 'sent_at' => PreciseInstant::class, 'first_accessed_at' => PreciseInstant::class, 'last_accessed_at' => PreciseInstant::class, 'revoked_at' => PreciseInstant::class];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id')->withTrashed();
    }

    public function events(): HasMany
    {
        return $this->hasMany(CustomerPortalEvent::class);
    }
}
