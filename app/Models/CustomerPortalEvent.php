<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerPortalEvent extends Model
{
    protected $fillable = ['person_id', 'customer_portal_access_id', 'event_type', 'subject_type', 'subject_id', 'metadata', 'ip_address', 'user_agent', 'occurred_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    public static function record(Person $customer, string $type, ?CustomerPortalAccess $access = null, ?Request $request = null, ?Model $subject = null, array $metadata = []): self
    {
        return self::create([
            'person_id' => $customer->id,
            'customer_portal_access_id' => $access?->id,
            'event_type' => $type,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 1000, '') : null,
            'occurred_at' => now(),
        ]);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id')->withTrashed();
    }
}
