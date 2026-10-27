<?php

namespace App\Services\Backup;

use DateTimeInterface;

/** Exact microseconds; legacy bigint seconds conservatively round up, never use float. */
class RecoveryDuration
{
    public static function microseconds(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return max(0, ((int) $to->format('U') - (int) $from->format('U')) * 1000000 + (int) $to->format('u') - (int) $from->format('u'));
    }

    public static function seconds(int $microseconds): int
    {
        return intdiv(max(0, $microseconds) + 999999, 1000000);
    }
}
