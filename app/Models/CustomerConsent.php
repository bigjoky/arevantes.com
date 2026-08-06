<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerConsent extends Model
{
    protected $fillable = ['person_id', 'recorded_by', 'channel', 'status', 'lawful_basis', 'source', 'captured_at', 'withdrawn_at', 'notes'];

    protected function casts(): array
    {
        return ['captured_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id')->withTrashed();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
