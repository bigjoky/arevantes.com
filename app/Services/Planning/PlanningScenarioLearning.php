<?php

namespace App\Services\Planning;

use App\Models\PlanningScenario;
use Illuminate\Support\Collection;

class PlanningScenarioLearning
{
    /** @return array<string, mixed>|null */
    public function for(PlanningScenario $scenario): ?array
    {
        if ($scenario->status !== 'closed' || ! $scenario->closure_snapshot || ! $scenario->closure_fingerprint) {
            return null;
        }

        $metrics = collect($scenario->closure_snapshot['areas'] ?? [])
            ->flatMap(fn (array $area): Collection => collect($area['comparison'] ?? [])->map(
                fn (array $metric): array => $this->metric((string) ($area['area'] ?? ''), $metric),
            ))
            ->values();

        $recommendations = $metrics
            ->whereIn('severity', ['watch', 'significant'])
            ->groupBy('area')
            ->map(fn (Collection $areaMetrics, string $area): array => [
                'area' => $area,
                'severity' => $areaMetrics->contains('severity', 'significant') ? 'significant' : 'watch',
                'message' => $this->recommendation($area),
                'metric_labels' => $areaMetrics->pluck('label')->values()->all(),
            ])
            ->values();

        return [
            'source' => 'immutable_closure',
            'closure_fingerprint' => $scenario->closure_fingerprint,
            'auto_apply' => false,
            'summary' => [
                'metrics' => $metrics->count(),
                'aligned' => $metrics->where('severity', 'aligned')->count(),
                'watch' => $metrics->where('severity', 'watch')->count(),
                'significant' => $metrics->where('severity', 'significant')->count(),
            ],
            'metrics' => $metrics->all(),
            'recommendations' => $recommendations->all(),
        ];
    }

    /** @param array<string, mixed> $metric @return array<string, mixed> */
    private function metric(string $area, array $metric): array
    {
        $planned = round((float) ($metric['planned'] ?? 0), 3);
        $current = round((float) ($metric['current'] ?? 0), 3);
        $delta = round($current - $planned, 3);
        $deltaPercent = abs($planned) > 0.0001 ? round(($delta / abs($planned)) * 100, 1) : null;

        return [
            'area' => $area,
            'label' => (string) ($metric['label'] ?? ''),
            'unit' => (string) ($metric['unit'] ?? 'items'),
            'planned' => $planned,
            'current' => $current,
            'delta' => $delta,
            'delta_percent' => $deltaPercent,
            'severity' => $this->severity($delta, $deltaPercent),
        ];
    }

    private function severity(float $delta, ?float $deltaPercent): string
    {
        if (abs($delta) < 0.0001) {
            return 'aligned';
        }
        if ($deltaPercent === null || abs($deltaPercent) >= 25) {
            return 'significant';
        }

        return abs($deltaPercent) >= 10 ? 'watch' : 'aligned';
    }

    private function recommendation(string $area): string
    {
        return match ($area) {
            'operations' => __('Review capacity and task-completion assumptions before the next scenario.'),
            'inventory' => __('Review material quantities and consumption assumptions before the next scenario.'),
            'purchases' => __('Review shortage quantities, supplier prices and purchasing assumptions before the next scenario.'),
            'finance' => __('Review collection timing and material-payment assumptions before the next scenario.'),
            default => __('Review this variance before preparing the next scenario.'),
        };
    }
}
