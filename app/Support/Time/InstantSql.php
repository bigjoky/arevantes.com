<?php

namespace App\Support\Time;

use App\Casts\PreciseInstant;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

final class InstantSql
{
    public static function forModel(string $class, string $column): string
    {
        $model = new $class;
        $field = basename(str_replace('.', '/', $column));
        if (! in_array($field, $model->preciseInstantFields(), true)) {
            throw new \InvalidArgumentException('Unregistered precise instant.');
        }

        return self::epoch($model->getConnection(), $column, $model->getCasts()[$field]);
    }

    public static function iso(string $class, string $column): string
    {
        $epoch = self::forModel($class, $column);

        return self::isoEpoch($epoch);
    }

    public static function legacyIso(string $column): string
    {
        return self::isoEpoch(self::legacyEpoch($column));
    }

    public static function legacyEpoch(string $column): string
    {
        $connection = DB::connection();
        $expression = self::epoch($connection, $column, PreciseInstant::class);

        return str_replace($connection->getQueryGrammar()->wrap($column.'_utc'), 'NULL', $expression);
    }

    private static function isoEpoch(string $epoch): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%dT%H:%M:%SZ', ($epoch), 'unixepoch')"
            : "to_char(to_timestamp($epoch) AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"')";
    }

    /** A read expression only: no historical value is rewritten. */
    public static function epoch(Connection $connection, string $column, string $cast): string
    {
        if (! preg_match('/^[a-z_]+(?:\.[a-z_]+)?$/', $column)) {
            throw new \InvalidArgumentException('Invalid temporal column.');
        }
        $grammar = $connection->getQueryGrammar();
        $wall = $grammar->wrap($column);
        $utc = $grammar->wrap($column.'_utc');
        $policy = str_contains($cast, ':latest') ? 'latest' : 'earliest';
        $offset = str_ends_with($cast, ',offset');
        if ($connection->getDriverName() === 'sqlite') {
            $connection->getPdo()->sqliteCreateFunction('arevantes_legacy_epoch', static function ($value, $policy) {
                if ($value === null) {
                    return null;
                }
                try {
                    return BusinessTime::legacy((string) $value, $policy)?->timestamp;
                } catch (\Throwable) {
                    return null;
                }
            }, 2);

            return "COALESCE(unixepoch($utc), arevantes_legacy_epoch($wall, '$policy'))";
        }
        if ($connection->getDriverName() !== 'pgsql' || BusinessTime::zone() !== 'Europe/Madrid') {
            throw new \LogicException('Precise instant SQL requires PostgreSQL/SQLite and the reviewed Europe/Madrid policy.');
        }
        if ($offset) {
            $legacy = $wall;
        } else {
            $base = "($wall AT TIME ZONE 'Europe/Madrid')";
            $alternative = "((($base AT TIME ZONE 'UTC') - interval '1 hour') AT TIME ZONE 'UTC')";
            $legacy = $policy === 'earliest'
                ? "CASE WHEN ($alternative AT TIME ZONE 'Europe/Madrid') = $wall THEN $alternative ELSE $base END"
                : $base;
            // A nonexistent wall time must not be silently normalized by PostgreSQL.
            $legacy = "CASE WHEN ($base AT TIME ZONE 'Europe/Madrid') = $wall THEN ($legacy) ELSE NULL END";
        }

        return "EXTRACT(EPOCH FROM (COALESCE($utc, ($legacy)) AT TIME ZONE 'UTC'))";
    }
}
