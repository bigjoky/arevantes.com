<?php

namespace App\Services\HumanResources;

use App\Models\HrLeavePolicy;
use App\Models\Person;
use Carbon\CarbonImmutable;

class LeaveBalanceCalculator
{
    /** @return array<string, float|int|string> */
    public function calculate(Person $person, HrLeavePolicy $policy, int $year): array
    {
        $record = $person->leaveBalances()->where('policy_id', $policy->id)->where('year', $year)->first();
        $entitlement = (float) $policy->annual_allowance_days;
        $accrued = $this->accrued($policy, $year, $entitlement);
        $carryover = min((float) ($record?->carryover_days ?? 0), (float) $policy->carryover_limit_days);
        $adjustment = (float) ($record?->adjustment_days ?? 0);
        $used = (float) $person->leaveRequests()
            ->where('type', $policy->type)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->sum('requested_days');

        return [
            'policyId' => $policy->id,
            'type' => $policy->type,
            'name' => $policy->name,
            'year' => $year,
            'entitlement' => $entitlement,
            'accrued' => round($accrued, 2),
            'carryover' => $carryover,
            'adjustment' => $adjustment,
            'used' => $used,
            'available' => round($accrued + $carryover + $adjustment - $used, 2),
        ];
    }

    private function accrued(HrLeavePolicy $policy, int $year, float $entitlement): float
    {
        if ($policy->accrual_method === 'none') {
            return 0;
        }

        if ($policy->accrual_method === 'annual' || $year < now()->year) {
            return $entitlement;
        }

        if ($year > now()->year) {
            return 0;
        }

        return $entitlement * CarbonImmutable::now()->month / 12;
    }
}
