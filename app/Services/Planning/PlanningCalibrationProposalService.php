<?php

namespace App\Services\Planning;

use App\Models\PlanningCalibrationProposal;
use App\Models\PlanningScenario;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanningCalibrationProposalService
{
    public const MINIMUM_SAMPLE_SIZE = 3;

    public function __construct(private PlanningScenarioLearning $learning) {}

    /** @return Collection<int, PlanningCalibrationProposal> */
    public function generate(User $actor): Collection
    {
        $groups = PlanningScenario::query()
            ->where('status', 'closed')
            ->whereNotNull('closure_snapshot')
            ->whereNotNull('closure_fingerprint')
            ->orderBy('closed_at')
            ->get()
            ->flatMap(fn (PlanningScenario $scenario): Collection => $this->evidenceFrom($scenario))
            ->groupBy(fn (array $row): string => hash('sha256', json_encode([
                $row['business_line'], $row['area'], $row['label'], $row['unit'],
            ], JSON_THROW_ON_ERROR)));

        return $groups
            ->map(fn (Collection $evidence): ?PlanningCalibrationProposal => $this->createFrom($evidence, $actor))
            ->filter()
            ->values();
    }

    public function decide(PlanningCalibrationProposal $proposal, User $actor, string $decision, string $note, mixed $acceptedValue = null): PlanningCalibrationProposal
    {
        return DB::transaction(function () use ($proposal, $actor, $decision, $note, $acceptedValue): PlanningCalibrationProposal {
            $locked = PlanningCalibrationProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['calibration' => __('Only a pending calibration proposal can be decided.')]);
            }
            $referenceValue = $decision === 'accepted' ? $this->acceptedValue($locked, $acceptedValue) : null;

            $locked->update([
                'status' => $decision,
                'accepted_value' => $referenceValue,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            return $locked->refresh();
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function evidenceFrom(PlanningScenario $scenario): Collection
    {
        $businessLines = collect($scenario->opportunity_snapshot['opportunities'] ?? [])
            ->pluck('confirmed.business_line')
            ->filter()
            ->unique()
            ->values();
        if ($businessLines->count() !== 1) {
            return collect();
        }

        $learning = $this->learning->for($scenario);

        return collect($learning['metrics'] ?? [])
            ->filter(fn (array $metric): bool => $metric['delta_percent'] !== null && abs((float) $metric['delta_percent']) >= 10)
            ->map(fn (array $metric): array => [
                'scenario_id' => $scenario->id,
                'scenario_name' => $scenario->name,
                'scenario_version' => $scenario->version,
                'closure_fingerprint' => $scenario->closure_fingerprint,
                'business_line' => $businessLines->first(),
                'area' => $metric['area'],
                'label' => $metric['label'],
                'unit' => $metric['unit'],
                'planned' => $metric['planned'],
                'observed' => $metric['current'],
                'delta' => $metric['delta'],
                'delta_percent' => $metric['delta_percent'],
            ]);
    }

    /** @param Collection<int, array<string, mixed>> $evidence */
    private function createFrom(Collection $evidence, User $actor): ?PlanningCalibrationProposal
    {
        $nonZero = $evidence->filter(fn (array $row): bool => abs((float) $row['delta']) > 0.0001);
        if ($nonZero->count() < self::MINIMUM_SAMPLE_SIZE) {
            return null;
        }

        $positive = $nonZero->where('delta', '>', 0)->count();
        $negative = $nonZero->where('delta', '<', 0)->count();
        $consistent = max($positive, $negative);
        $consistency = round($consistent / $nonZero->count(), 3);
        if ($consistency < 0.75) {
            return null;
        }

        $direction = $positive > $negative ? 'higher' : 'lower';
        $consistentEvidence = $nonZero->filter(fn (array $row): bool => $direction === 'higher' ? $row['delta'] > 0 : $row['delta'] < 0)->values();
        $first = $evidence->first();
        $metricKey = hash('sha256', json_encode([$first['area'], $first['label'], $first['unit']], JSON_THROW_ON_ERROR));
        $sourceFingerprints = $evidence->pluck('closure_fingerprint')->sort()->values()->all();
        $sourceFingerprint = hash('sha256', json_encode([
            $first['business_line'], $metricKey, $sourceFingerprints,
        ], JSON_THROW_ON_ERROR));

        if (PlanningCalibrationProposal::where('source_fingerprint', $sourceFingerprint)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($actor, $consistency, $consistentEvidence, $direction, $evidence, $first, $metricKey, $sourceFingerprint): ?PlanningCalibrationProposal {
            // Transaction advisory lock covers an empty series too; unique indexes remain the final guard.
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['calibration:'.$first['business_line'].':'.$metricKey]);
            }
            if (PlanningCalibrationProposal::where('source_fingerprint', $sourceFingerprint)->exists()) {
                return null;
            }
            $version = ((int) PlanningCalibrationProposal::query()
                ->where('business_line', $first['business_line'])
                ->where('metric_key', $metricKey)
                ->max('version')) + 1;

            return PlanningCalibrationProposal::create([
                'business_line' => $first['business_line'],
                'area' => $first['area'],
                'metric_label' => $first['label'],
                'metric_key' => $metricKey,
                'unit' => $first['unit'],
                'version' => $version,
                'status' => 'pending',
                'direction' => $direction,
                'sample_size' => $evidence->count(),
                'evidence_snapshot' => [
                    'minimum_sample_size' => self::MINIMUM_SAMPLE_SIZE,
                    'consistent_samples' => $consistentEvidence->count(),
                    'direction_consistency' => $consistency,
                    'average_delta_percent' => round((float) $consistentEvidence->avg('delta_percent'), 1),
                    'planned_total' => round((float) $consistentEvidence->sum('planned'), 3),
                    'observed_total' => round((float) $consistentEvidence->sum('observed'), 3),
                    'sources' => $evidence->map(fn (array $row): array => [
                        'scenario_id' => $row['scenario_id'],
                        'scenario_name' => $row['scenario_name'],
                        'scenario_version' => $row['scenario_version'],
                        'closure_fingerprint' => $row['closure_fingerprint'],
                        'delta_percent' => $row['delta_percent'],
                    ])->values()->all(),
                ],
                'source_fingerprint' => $sourceFingerprint,
                'recommendation' => $this->recommendation($first['area'], $direction),
                'assumption_key' => $this->assumptionKey($first['area']),
                'created_by' => $actor->id,
            ]);
        });
    }

    private function acceptedValue(PlanningCalibrationProposal $proposal, mixed $value): float|int
    {
        if ($proposal->assumption_key === null || ! is_numeric($value)) {
            throw ValidationException::withMessages(['accepted_value' => __('Accepted calibration proposals require a valid reference value.')]);
        }

        $numeric = (float) $value;
        [$maximum, $integer] = match ($proposal->assumption_key) {
            'capacity_hours_per_opportunity' => [1000, false],
            'material_cost_percent_without_quote' => [100, false],
            'collection_delay_days' => [365, true],
            default => throw ValidationException::withMessages(['accepted_value' => __('This calibration metric is not linked to an editable scenario assumption.')]),
        };
        if ($numeric < 0 || $numeric > $maximum || ($integer && floor($numeric) !== $numeric)) {
            throw ValidationException::withMessages(['accepted_value' => __('The calibration reference value is outside the allowed range.')]);
        }

        return $integer ? (int) $numeric : round($numeric, 3);
    }

    private function assumptionKey(string $area): ?string
    {
        return match ($area) {
            'operations' => 'capacity_hours_per_opportunity',
            'inventory', 'purchases' => 'material_cost_percent_without_quote',
            'finance' => 'collection_delay_days',
            default => null,
        };
    }

    private function recommendation(string $area, string $direction): string
    {
        $observation = $direction === 'higher' ? 'repeatedly exceeded the plan' : 'repeatedly remained below the plan';

        return match ($area) {
            'operations' => "Operational outcomes {$observation}; review capacity and task assumptions in the next scenario.",
            'inventory' => "Material outcomes {$observation}; review quantity and consumption assumptions in the next scenario.",
            'purchases' => "Purchasing outcomes {$observation}; review shortage, price and supplier assumptions in the next scenario.",
            'finance' => "Financial outcomes {$observation}; review collection and payment assumptions in the next scenario.",
            default => "Observed outcomes {$observation}; review the related assumptions in the next scenario.",
        };
    }
}
