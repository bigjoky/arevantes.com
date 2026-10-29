<?php

namespace App\Models;

use App\Casts\UtcTimestamp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AccountingReclassificationBatch extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['batch' => __('Accounting corrections are immutable.')]));
        static::deleting(fn () => throw ValidationException::withMessages(['batch' => __('Accounting corrections are immutable.')]));
    }

    protected function casts(): array
    {
        return ['selection' => 'array', 'results' => 'array', 'recorded_at_utc' => UtcTimestamp::class];
    }
}
