<?php

namespace App\Casts;

use App\Support\Time\BusinessTime;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PreciseInstant implements CastsAttributes, SerializesCastableAttributes
{
    public bool $withoutObjectCaching = true;

    public function __construct(private string $legacyPolicy = 'earliest', private string $legacyStorage = 'wall') {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        if (! empty($attributes[$key.'_utc'])) {
            return Carbon::parse($attributes[$key.'_utc'], 'UTC')->setTimezone(BusinessTime::zone());
        }

        return BusinessTime::legacy((string) $value, $this->legacyPolicy);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        return BusinessTime::storage($key, $value, $this->legacyStorage === 'offset');
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->get($model, $key, $attributes[$key] ?? null, $attributes)?->toISOString();
    }
}
