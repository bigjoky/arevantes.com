<?php

namespace App\Database;

use App\Support\Time\BusinessTime;
use App\Support\Time\InstantSql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InstantBuilder extends Builder
{
    public function completeInstantProjection(?array $columns = null): void
    {
        $query = $this->getQuery();
        $columns ??= $query->columns;
        if ($columns === null) {
            return;
        }
        foreach ($this->getModel()->preciseInstantFields() as $field) {
            foreach ([$field, $this->getModel()->qualifyColumn($field)] as $column) {
                if (in_array($column, $columns, true)) {
                    $utc = str_contains($column, '.') ? $this->getModel()->qualifyColumn($field.'_utc') : $field.'_utc';
                    if (($query->groups || $query->unions || $query->distinct) && ! in_array($utc, $columns, true)) {
                        throw new \LogicException('Select the UTC companion explicitly for grouped, distinct or union instant projections.');
                    }
                    if (! in_array($utc, $columns, true)) {
                        $columns[] = $utc;
                    }
                }
            }
        }
        $query->columns = $columns;
    }

    public function getModels($columns = ['*'])
    {
        // get([...]) chooses columns after global scopes have run, unlike select([...])->get().
        $this->completeInstantProjection($this->getQuery()->columns ?? (array) $columns);

        return parent::getModels($columns);
    }

    private function instantField(mixed $column): ?string
    {
        if (! is_string($column)) {
            return null;
        }
        $parts = explode('.', $column);
        $field = end($parts);
        if (count($parts) > 1 && $parts[0] !== $this->getModel()->getTable()) {
            return null;
        }

        return in_array($field, $this->getModel()->preciseInstantFields(), true) ? $field : null;
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        if (($field = $this->instantField($column)) && $value !== null && in_array($operator, ['<', '<=', '>', '>=', '=', '!=', '<>'], true)) {
            $sql = InstantSql::epoch($this->getConnection(), $this->getModel()->qualifyColumn($field), $this->getModel()->getCasts()[$field]);
            $this->getQuery()->whereRaw("($sql) $operator ?", [BusinessTime::parse($value, $field)->timestamp], $boolean);

            return $this;
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false)
    {
        if ($field = $this->instantField($column)) {
            $sql = InstantSql::epoch($this->getConnection(), $this->getModel()->qualifyColumn($field), $this->getModel()->getCasts()[$field]);
            $this->getQuery()->whereBetween(DB::raw($sql), array_map(fn ($value) => BusinessTime::parse($value, $field)->timestamp, [...$values]), $boolean, $not);
        } else {
            $this->getQuery()->whereBetween($column, $values, $boolean, $not);
        }

        return $this;
    }

    public function orWhereBetween($column, iterable $values)
    {
        return $this->whereBetween($column, $values, 'or');
    }

    public function whereNotBetween($column, iterable $values, $boolean = 'and')
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    public function orWhereNotBetween($column, iterable $values)
    {
        return $this->whereBetween($column, $values, 'or', true);
    }

    public function pluck($column, $key = null)
    {
        if ($this->instantField($column)) {
            $columns = [$column, $column.'_utc'];
            if ($key !== null) {
                $columns[] = $key;
            }

            return $this->get(array_unique($columns))->pluck($this->instantField($column), $key);
        }

        return parent::pluck($column, $key);
    }

    public function orderBy($column, $direction = 'asc')
    {
        if ($field = $this->instantField($column)) {
            $column = DB::raw(InstantSql::epoch($this->getConnection(), $this->getModel()->qualifyColumn($field), $this->getModel()->getCasts()[$field]));
        }
        $this->getQuery()->orderBy($column, $direction);

        return $this;
    }

    public function orderByDesc($column)
    {
        return $this->orderBy($column, 'desc');
    }

    public function latest($column = null)
    {
        return $this->orderBy($column ?? $this->getModel()->getCreatedAtColumn(), 'desc');
    }

    public function oldest($column = null)
    {
        return $this->orderBy($column ?? $this->getModel()->getCreatedAtColumn(), 'asc');
    }

    private function preciseValues(array $values): array
    {
        foreach ($this->getModel()->preciseInstantFields() as $field) {
            if (array_key_exists($field, $values)) {
                $input = $values[$field] === null ? null : ($values[$field.'_utc'] ?? $values[$field]);
                $values = [...$values, ...BusinessTime::storage($field, $input, str_ends_with($this->getModel()->getCasts()[$field], ',offset'))];
            }
        }

        return $values;
    }

    public function insert(array $values)
    {
        if ($values === []) {
            return true;
        }
        $rows = is_array(reset($values)) ? $values : [$values];

        return $this->getQuery()->insert(array_map(fn ($row) => $this->preciseValues($row), $rows));
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if ($values === []) {
            return 0;
        }
        $rows = is_array(reset($values)) ? $values : [$values];
        $update ??= array_keys(reset($rows));
        if ($this->getModel()->usesTimestamps() && in_array('created_at', $this->getModel()->preciseInstantFields(), true)) {
            $rows = array_map(fn ($row) => ['created_at' => BusinessTime::now(), ...$row], $rows);
        }
        $rows = array_map(fn ($row) => $this->preciseValues($row), $rows);
        if ($update !== null) {
            foreach ($this->getModel()->preciseInstantFields() as $field) {
                if (in_array($field, $update, true)) {
                    $update[] = $field.'_utc';
                }
            }
        }

        return parent::upsert($rows, $uniqueBy, $update);
    }

    public function update(array $values)
    {
        return parent::update($this->preciseValues($values));
    }
}
