<?php

namespace App\Services\Engineering;

use App\Models\EngineeringScheduleProposal;
use App\Services\Work\TechnicianSchedule;
use App\Support\Time\BusinessTime;

class TechnicalScheduleAvailability
{
    public function check(EngineeringScheduleProposal $proposal, string $startTime): array
    {
        $start = BusinessTime::parse($proposal->proposed_on->toDateString().' '.$startTime, 'start_time');
        $end = $start->copy()->addMinutes((int) round((float) $proposal->estimated_hours * 60));
        $decision = app(TechnicianSchedule::class)->availability($proposal->assignee_id, $start, $end);
        $issues = $decision['issues'];
        if ($start->lt(BusinessTime::now())) {
            $issues[] = __('The proposed time has already passed.');
        }
        if ($end->gt($start->copy()->startOfDay()->addDay())) {
            $issues[] = __('The proposed work must fit within one calendar day.');
        }

        return [
            'status' => $issues === [] ? 'clear' : 'blocked',
            'availability' => $decision['status'],
            'checked_at' => BusinessTime::now()->toIso8601String(), 'timezone' => BusinessTime::zone(),
            'start' => $start->toIso8601String(), 'end' => $end->toIso8601String(),
            'issues' => array_values(array_unique($issues)), 'warnings' => [],
        ];
    }
}
