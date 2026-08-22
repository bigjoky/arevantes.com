<?php

namespace App\Services\HumanResources;

use App\Models\HrLeavePolicy;
use App\Models\HrLeaveRequest;
use App\Models\Person;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class LeaveBalanceCalculator
{
    public function __construct(private LeaveDayCalculator $days) {}

    /** @return array<string, float|int|string> */
    public function calculate(Person $person, HrLeavePolicy $policy, int $year): array
    {
        $record = $person->leaveBalances()->where('policy_id', $policy->id)->where('year', $year)->first();
        $entitlement = (float) $policy->annual_allowance_days;
        $accrued = $this->accrued($person, $policy, $year, $entitlement);
        $carryover = min((float) ($record?->carryover_days ?? 0), (float) $policy->carryover_limit_days);
        $adjustment = (float) ($record?->adjustment_days ?? 0);
        $requests = $person->leaveRequests()
            ->where('type', $policy->type)
            ->whereIn('status', ['approved', 'pending'])
            ->whereDate('start_date', '<=', "{$year}-12-31")
            ->whereDate('end_date', '>=', "{$year}-01-01")
            ->get(['start_date', 'end_date', 'status']);
        $used = $requests->where('status', 'approved')
            ->sum(fn (HrLeaveRequest $leave) => $this->daysInYear($leave, $policy, $year));
        $pending = $requests->where('status', 'pending')
            ->sum(fn (HrLeaveRequest $leave) => $this->daysInYear($leave, $policy, $year));
        $available = $accrued + $carryover + $adjustment - $used;

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
            'pending' => $pending,
            'available' => round($available, 2),
            'requestable' => round($available - $pending, 2),
        ];
    }

    public function assertRequestable(Person $person, HrLeavePolicy $policy, CarbonImmutable $start, CarbonImmutable $end): void
    {
        if ($policy->accrual_method === 'none') {
            return;
        }
        foreach (range($start->year, $end->year) as $year) {
            $required = $this->days->count(
                $start->max(CarbonImmutable::create($year, 1, 1)),
                $end->min(CarbonImmutable::create($year, 12, 31)),
                $policy->day_count_method,
            );
            $balance = $this->calculate($person, $policy, $year);
            if ($required > $balance['requestable']) {
                throw ValidationException::withMessages([
                    'start_date' => __('The request exceeds the available leave balance for :year (:available days available).', [
                        'year' => $year,
                        'available' => $balance['requestable'],
                    ]),
                ]);
            }
        }
    }

    public function assertApprovable(Person $person, HrLeavePolicy $policy, HrLeaveRequest $leave): void
    {
        if ($policy->accrual_method === 'none') {
            return;
        }
        foreach (range($leave->start_date->year, $leave->end_date->year) as $year) {
            $required = $this->daysInYear($leave, $policy, $year);
            $balance = $this->calculate($person, $policy, $year);
            if ($required > $balance['available']) {
                throw ValidationException::withMessages([
                    'status' => __('This request cannot be approved because the available balance for :year is :available days.', [
                        'year' => $year,
                        'available' => $balance['available'],
                    ]),
                ]);
            }
        }
    }

    private function daysInYear(HrLeaveRequest $leave, HrLeavePolicy $policy, int $year): int
    {
        return $this->days->count(
            $leave->start_date->max(CarbonImmutable::create($year, 1, 1)),
            $leave->end_date->min(CarbonImmutable::create($year, 12, 31)),
            $policy->day_count_method,
        );
    }

    private function accrued(Person $person, HrLeavePolicy $policy, int $year, float $entitlement): float
    {
        if ($policy->accrual_method === 'none') {
            return 0;
        }

        if ($policy->accrual_method === 'annual') {
            return $entitlement;
        }

        $employment = $person->employmentProfile;
        if ($employment?->start_date?->year > $year || ($employment?->end_date && $employment->end_date->year < $year)) {
            return 0;
        }

        $firstMonth = $employment?->start_date?->year === $year ? $employment->start_date->month : 1;
        $lastMonth = match (true) {
            $employment?->end_date?->year === $year => $employment->end_date->month,
            $year < now()->year, $year > now()->year => 12,
            default => CarbonImmutable::now()->month,
        };

        return $entitlement * max(0, $lastMonth - $firstMonth + 1) / 12;
    }
}
