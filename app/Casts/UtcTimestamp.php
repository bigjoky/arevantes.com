<?php

namespace App\Casts;

use App\Support\Time\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/** A new UTC-only column; never apply to unclassified legacy wall times. */
class UtcTimestamp implements CastsAttributes, SerializesCastableAttributes
{
    public bool $withoutObjectCaching = true;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value, 'UTC')->utc();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : BusinessTime::utc($value, $key);
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->get($model, $key, $attributes[$key] ?? null, $attributes)?->toISOString();
    }
}
