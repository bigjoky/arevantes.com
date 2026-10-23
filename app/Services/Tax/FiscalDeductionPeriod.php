<?php

namespace App\Services\Tax;

use App\Models\AccountingPeriod;
use App\Models\TaxReturn;
use App\Services\Accounting\AccountingPeriodManager;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/** Shared rules for reviewed received invoices; callers hold the source fiscal-year lock. */
class FiscalDeductionPeriod
{
    public function explicit(mixed $review): bool
    {
        return $review && $review->registered_on && $review->deduction_year === 2026
            && in_array($review->deduction_quarter, [2, 3, 4], true);
    }

    public function validate(CarbonInterface $invoiceDate, array $data): array
    {
        if (! filled($data['registered_on'] ?? null) || ! isset($data['deduction_year'], $data['deduction_quarter'])) {
            $this->fail('Confirm the registration date and deduction period before reviewing.');
        }
        $registered = CarbonImmutable::parse($data['registered_on']);
        $year = (int) $data['deduction_year'];
        $quarter = (int) $data['deduction_quarter'];
        if ($year !== 2026 || $invoiceDate->year !== 2026 || $registered->year !== 2026
            || ! in_array($quarter, [2, 3, 4], true) || $registered->lt($invoiceDate->copy()->startOfDay())
            || $registered->startOfDay()->gt(today()) || $quarter < $registered->quarter) {
            $this->fail('Only supported 2026 deduction periods on or after registration are available.');
        }

        return ['registered_on' => $registered->toDateString(), 'deduction_year' => $year, 'deduction_quarter' => $quarter];
    }

    public function stored(mixed $review): ?array
    {
        return $review?->deduction_year && $review?->deduction_quarter ? [
            'registered_on' => $review->registered_on?->toDateString(),
            'deduction_year' => $review->deduction_year, 'deduction_quarter' => $review->deduction_quarter,
        ] : null;
    }

    public function protectionIssues(?array $period): array
    {
        if (! $period) {
            return [];
        }
        $start = CarbonImmutable::create($period['deduction_year'], ($period['deduction_quarter'] - 1) * 3 + 1, 1)->startOfDay();
        $end = $start->endOfQuarter();
        $registered = $period['registered_on'] ? CarbonImmutable::parse($period['registered_on']) : null;
        if (AccountingPeriod::where('year', $period['deduction_year'])->whereBetween('month', [$start->month, $end->month])->where('status', 'closed')->exists()
            || ($registered && AccountingPeriod::where('year', $registered->year)->where('month', $registered->month)->where('status', 'closed')->exists())
            || TaxReturn::where('tax_type', 'vat')->whereIn('status', ['prepared', 'filed'])
                ->whereDate('period_from', '<=', $end)->whereDate('period_to', '>=', $start)->exists()) {
            return ['The previous or selected deduction period is protected.'];
        }

        return [];
    }

    public function assertOpen(?array $period): void
    {
        if (! $period) {
            return;
        }
        if ($period['registered_on']) {
            app(AccountingPeriodManager::class)->assertOpen($period['registered_on']);
        }
        if ($this->protectionIssues($period) !== []) {
            $this->fail('The previous or selected deduction period is protected.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['operation_class' => __($message)]);
    }
}
