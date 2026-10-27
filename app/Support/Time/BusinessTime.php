<?php

namespace App\Support\Time;

use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class BusinessTime
{
    public static function zone(): string
    {
        return config('app.timezone', 'Europe/Madrid');
    }

    public static function now(): Carbon
    {
        // Obtain the clock as an instant before applying the presentation/business zone.
        return Carbon::now('UTC')->setTimezone(self::zone());
    }

    /** Resolve wall time by enumerating offsets, rather than PHP's implicit DST choice. */
    public static function candidates(string $value): array
    {
        $value = str_replace('T', ' ', trim($value));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $value .= ' 00:00:00';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }
        if (! preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?$/', $value, $parts)) {
            return [];
        }
        $fraction = str_pad($parts[2] ?? '', 6, '0');
        try {
            $wall = Carbon::createFromFormat('!Y-m-d H:i:s.u', $parts[1].'.'.$fraction, 'UTC');
        } catch (\Throwable) {
            return [];
        }
        if (! $wall || $wall->format('Y-m-d H:i:s') !== $parts[1]) {
            return [];
        }
        $zone = new DateTimeZone(self::zone());
        $transitions = $zone->getTransitions($wall->timestamp - 172800, $wall->timestamp + 172800);
        $offsets = $transitions === false ? [$zone->getOffset($wall)] : array_unique(array_column($transitions, 'offset'));
        $candidates = [];
        foreach ($offsets as $offset) {
            $candidate = $wall->copy()->subSeconds($offset)->setTimezone($zone);
            if ($candidate->format('Y-m-d H:i:s') === $parts[1]) {
                $candidates[$candidate->timestamp] = $candidate;
            }
        }
        ksort($candidates);

        return array_values($candidates);
    }

    public static function parse(mixed $value, string $field = 'datetime'): Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->setTimezone(self::zone());
        }
        $value = trim((string) $value);
        if (preg_match('/[T ]\d{2}:\d{2}.*(?:Z|[+-]\d{2}(?::?\d{2})?)$/i', $value)) {
            if (! preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(?::(\d{2})(?:\.\d{1,6})?)?(Z|[+-]\d{2}(?::?\d{2})?)$/i', $value, $parts)) {
                throw ValidationException::withMessages([$field => 'El instante temporal no es válido.']);
            }
            $civil = $parts[1].' '.$parts[2].':'.($parts[3] ?: '00');
            $check = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $civil, new DateTimeZone('UTC'));
            $offset = preg_replace('/[^0-9]/', '', $parts[4]);
            if (! $check || $check->format('Y-m-d H:i:s') !== $civil || ($offset !== '' && ((int) substr($offset, 0, 2) > 14 || (int) substr($offset, 2, 2) > 59 || ((int) substr($offset, 0, 2) === 14 && (int) substr($offset, 2, 2) !== 0)))) {
                throw ValidationException::withMessages([$field => 'El instante temporal no es válido.']);
            }
            try {
                return Carbon::parse($value)->setTimezone(self::zone());
            } catch (\Throwable) {
                throw ValidationException::withMessages([$field => 'El instante temporal no es válido.']);
            }
        }
        $candidates = self::candidates($value);
        if (count($candidates) !== 1) {
            throw ValidationException::withMessages([$field => count($candidates) > 1
                ? 'Esta hora se repite por el cambio de horario. Indica un instante con desplazamiento explícito (+02:00 o +01:00).'
                : 'Esta hora no existe o no es válida en la zona horaria de la empresa.']);
        }

        return $candidates[0];
    }

    public static function classify(?string $wall, ?string $utc): string
    {
        if ($wall === null && $utc === null) {
            return 'empty';
        }
        try {
            if ($utc !== null) {
                if ($wall === null) {
                    return 'divergent';
                }
                if (! preg_match('/[T ]\d{2}:\d{2}.*(?:Z|[+-]\d{2}(?::?\d{2})?)$/i', $utc)) {
                    return 'divergent';
                }
                $instant = self::parse($utc);
                $matches = self::candidates($wall);
                if ($matches === []) {
                    $legacy = self::legacy($wall, 'earliest');
                    $matches = $legacy ? [$legacy] : [];
                }

                return collect($matches)->contains(fn ($candidate) => $candidate->equalTo($instant)) ? 'precise' : 'divergent';
            }

            return match (count(self::candidates($wall))) {
                2 => 'ambiguous',
                1 => 'legacy_unambiguous',
                default => self::legacy($wall, 'earliest') ? 'legacy_unambiguous' : 'invalid_or_nonexistent',
            };
        } catch (\Throwable) {
            return $utc !== null ? 'divergent' : 'invalid_or_nonexistent';
        }
    }

    public static function utc(mixed $value, string $field = 'datetime'): string
    {
        return self::parse($value, $field)->utc()->format('Y-m-d H:i:s.uP');
    }

    /** Read legacy values without rewriting them; earliest expiry / latest publication is conservative. */
    public static function legacy(string $value, string $policy): ?Carbon
    {
        if (preg_match('/[T ]\d{2}:\d{2}.*(?:Z|[+-]\d{2}(?::?\d{2})?)$/i', $value)) {
            return self::parse($value);
        }
        $candidates = self::candidates($value);
        if ($candidates === []) {
            return null;
        }

        return $policy === 'latest' ? $candidates[array_key_last($candidates)] : $candidates[0];
    }

    public static function storage(string $field, mixed $value, bool $offsetLegacy = false): array
    {
        if ($value === null) {
            return [$field => null, $field.'_utc' => null];
        }
        // Existing domain timestamps have second resolution; preserve that contract.
        $instant = self::parse($value, $field)->startOfSecond();

        return [$field => $offsetLegacy ? $instant->toIso8601String() : $instant->format('Y-m-d H:i:s'), $field.'_utc' => self::utc($instant)];
    }
}
