<?php

namespace App\Services\HumanResources;

use App\Models\HrCalendarHoliday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class LeaveDayCalculator
{
    /** @var array<int, array<string, true>> */
    private array $holidaysByYear = [];

    public function count(CarbonInterface|string $start, CarbonInterface|string $end, string $method): int
    {
        $current = CarbonImmutable::parse($start)->startOfDay();
        $last = CarbonImmutable::parse($end)->startOfDay();
        if ($current->greaterThan($last)) {
            return 0;
        }
        if ($method === 'calendar_days') {
            return $current->diffInDays($last) + 1;
        }

        $days = 0;
        while ($current->lessThanOrEqualTo($last)) {
            if ($current->isWeekday() && ! isset($this->holidays($current->year)[$current->toDateString()])) {
                $days++;
            }
            $current = $current->addDay();
        }

        return $days;
    }

    /** @return array<string, true> */
    private function holidays(int $year): array
    {
        return $this->holidaysByYear[$year] ??= HrCalendarHoliday::query()
            ->whereBetween('holiday_date', ["{$year}-01-01", "{$year}-12-31"])
            ->pluck('holiday_date')
            ->mapWithKeys(fn ($date) => [CarbonImmutable::parse($date)->toDateString() => true])
            ->all();
    }
}
