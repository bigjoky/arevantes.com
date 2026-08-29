<?php

namespace App\Services\AfterSales;

use App\Models\ServiceCorrectivePlan;
use App\Models\ServiceCycleCommitment;
use App\Models\ServiceCycleDeviation;

class RecurringServiceEffectivenessEvaluator
{
    /** @return array<string, mixed> */
    public function evaluate(ServiceCorrectivePlan $plan): array
    {
        $baseline = $plan->baseline_snapshot;
        $categories = collect($baseline['categories'] ?? [])->values();
        $cycles = ServiceCycleCommitment::query()
            ->where('maintenance_plan_id', $plan->maintenance_plan_id)
            ->where('status', 'completed')
            ->where('completed_at', '>', $baseline['cutoff_completed_at'])
            ->orderBy('completed_at')
            ->limit($plan->verification_cycles)
            ->get(['id', 'planned_minutes', 'completion_snapshot', 'completed_at']);
        $ready = $cycles->count() >= $plan->verification_cycles;
        $deviations = $cycles->isEmpty() ? collect() : ServiceCycleDeviation::query()
            ->whereIn('service_cycle_commitment_id', $cycles->pluck('id'))
            ->whereIn('category', $categories)
            ->whereIn('status', ['confirmed', 'resolved'])
            ->get(['id', 'category', 'variance_percent', 'tolerance_percent']);
        $opportunities = max(1, $cycles->count() * max(1, $categories->count()));
        $afterRate = round($deviations->count() / $opportunities, 4);
        $afterExcess = $this->meanExcess($deviations);
        $beforeRate = (float) ($baseline['deviation_rate'] ?? 0);
        $beforeExcess = (float) ($baseline['mean_excess_percent'] ?? 0);
        $rateImproved = $afterRate < $beforeRate;
        $excessImproved = $beforeExcess > 0 ? $afterExcess <= $beforeExcess * 0.8 : $afterExcess === 0.0;
        $notWorse = $afterRate <= $beforeRate && $afterExcess <= $beforeExcess;

        return [
            'ready' => $ready,
            'required_cycles' => $plan->verification_cycles,
            'observed_cycles' => $cycles->count(),
            'remaining_cycles' => max(0, $plan->verification_cycles - $cycles->count()),
            'categories' => $categories->all(),
            'before' => [
                'cycles' => (int) data_get($baseline, 'volume.cycles', 0),
                'opportunities' => (int) ($baseline['opportunities'] ?? 0),
                'deviations' => (int) ($baseline['deviation_count'] ?? 0),
                'deviation_rate' => $beforeRate,
                'mean_excess_percent' => $beforeExcess,
                'planned_minutes_per_cycle' => (float) data_get($baseline, 'volume.planned_minutes_per_cycle', 0),
            ],
            'after' => [
                'cycles' => $cycles->count(),
                'opportunities' => $opportunities,
                'deviations' => $deviations->count(),
                'deviation_rate' => $afterRate,
                'mean_excess_percent' => $afterExcess,
                'planned_minutes_per_cycle' => round((float) $cycles->avg('planned_minutes'), 2),
                'cycle_ids' => $cycles->pluck('id')->all(),
                'deviation_ids' => $deviations->pluck('id')->all(),
            ],
            'volume_change_percent' => $this->changePercent(
                (float) data_get($baseline, 'volume.planned_minutes_per_cycle', 0),
                (float) $cycles->avg('planned_minutes'),
            ),
            'recommended_result' => $ready ? (($rateImproved || $excessImproved) && $notWorse ? 'effective' : 'ineffective') : null,
            'evaluated_at' => now()->toISOString(),
        ];
    }

    private function meanExcess(mixed $deviations): float
    {
        if ($deviations->isEmpty()) {
            return 0.0;
        }

        return round((float) $deviations->avg(fn ($deviation) => max(0, abs((float) $deviation->variance_percent) - (float) $deviation->tolerance_percent)), 2);
    }

    private function changePercent(float $before, float $after): ?float
    {
        return $before > 0 ? round(($after - $before) / $before * 100, 2) : null;
    }
}
