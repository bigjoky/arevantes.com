<?php

namespace App\Support;

final class PhoneNormalizer
{
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $phone)) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 9) {
            $digits = '34'.$digits;
        }

        return $digits !== '' ? $digits : null;
    }

    public static function isPlausibleMobile(?string $phone): bool
    {
        $normalized = self::normalize($phone);

        return $normalized !== null
            && strlen($normalized) >= 9
            && strlen($normalized) <= 15;
    }
}
