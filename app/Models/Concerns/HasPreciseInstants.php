<?php

namespace App\Models\Concerns;

use App\Casts\PreciseInstant;
use App\Database\InstantBuilder;
use App\Support\Time\BusinessTime;
use App\Support\Time\InstantSql;
use Illuminate\Database\Eloquent\Builder;

trait HasPreciseInstants
{
    public function newEloquentBuilder($query): InstantBuilder
    {
        return new InstantBuilder($query);
    }

    public static function bootHasPreciseInstants(): void
    {
        static::addGlobalScope('precise_instants', function (Builder $builder): void {
            $builder->completeInstantProjection();
        });
    }

    public function freshTimestamp()
    {
        return BusinessTime::now();
    }

    public function getHidden(): array
    {
        return array_values(array_unique([...parent::getHidden(), ...array_map(fn ($field) => $field.'_utc', $this->preciseInstantFields())]));
    }

    public function initializeHasPreciseInstants(): void
    {
        $this->mergeHidden(array_map(fn ($field) => $field.'_utc', $this->preciseInstantFields()));
    }

    public function preciseInstantFields(): array
    {
        return array_keys(array_filter($this->getCasts(), fn ($cast) => is_string($cast) && str_starts_with($cast, PreciseInstant::class)));
    }

    // Preserve DateTime offsets for created_at too, before Eloquent formats dates for legacy columns.
    protected function isDateAttribute($key)
    {
        return in_array($key, $this->preciseInstantFields(), true) ? false : parent::isDateAttribute($key);
    }

    public function scopeWhereInstant(Builder $query, string $field, string $operator, mixed $value): Builder
    {
        if (! in_array($field, $this->preciseInstantFields(), true) || ! in_array($operator, ['<', '<=', '>', '>=', '='], true)) {
            throw new \InvalidArgumentException('Unsupported instant comparison.');
        }
        $at = BusinessTime::parse($value, $field);
        $expression = InstantSql::epoch($this->getConnection(), $this->qualifyColumn($field), $this->getCasts()[$field]);

        return $query->whereRaw("($expression) $operator ?", [$at->timestamp]);
    }
}
