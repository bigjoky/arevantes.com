<?php

namespace App\Models;

use App\Casts\UtcTimestamp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class AccountingEntryCorrection extends Model
{
    public $timestamps = false;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['line_changes' => 'array', 'recorded_at_utc' => UtcTimestamp::class];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['correction' => __('Accounting corrections are immutable.')]));
        static::deleting(fn () => throw ValidationException::withMessages(['correction' => __('Accounting corrections are immutable.')]));
    }
}
