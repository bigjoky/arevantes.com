<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class CommercialFieldSession extends Model
{
    protected $fillable = ['user_id', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
    }

    // These new operational timestamps use UTC, so elapsed time remains real across DST.
    protected function startedAt(): Attribute
    {
        return $this->utcInstant();
    }

    protected function endedAt(): Attribute
    {
        return $this->utcInstant();
    }

    private function utcInstant(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : CarbonImmutable::parse($value, 'UTC'),
            set: fn ($value) => $value === null ? null : CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s'),
        );
    }
}
