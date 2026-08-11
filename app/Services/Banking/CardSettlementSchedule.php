<?php

namespace App\Services\Banking;

use App\Models\BankFinancialProduct;
use Carbon\CarbonImmutable;

class CardSettlementSchedule
{
    /** @return array{closingOn: CarbonImmutable, paymentOn: CarbonImmutable} */
    public function next(BankFinancialProduct $creditPool, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::today();
        $closingDay = $creditPool->billing_day ?? 31;
        $paymentDay = $creditPool->payment_day ?? 5;
        $closingOn = $this->dayInMonth($asOf, $closingDay);

        if ($asOf->isAfter($closingOn)) {
            $closingOn = $this->dayInMonth($asOf->addMonthNoOverflow(), $closingDay);
        }

        $paymentMonth = $closingOn->addMonthNoOverflow()->startOfMonth();

        return [
            'closingOn' => $closingOn,
            'paymentOn' => $this->dayInMonth($paymentMonth, $paymentDay),
        ];
    }

    private function dayInMonth(CarbonImmutable $month, int $day): CarbonImmutable
    {
        return $month->startOfMonth()->day(min($day, $month->daysInMonth));
    }
}
