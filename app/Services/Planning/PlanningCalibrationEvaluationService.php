<?php

namespace App\Services\Planning;

use App\Models\PlanningCalibrationEvaluation;
use App\Models\PlanningCalibrationProposal;
use App\Models\PlanningScenario;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanningCalibrationEvaluationService
{
    /** @return Collection<int, PlanningCalibrationEvaluation> */
    public function generate(User $actor): Collection
    {
        $proposals = PlanningCalibrationProposal::query()->get()->keyBy('id');

        return PlanningScenario::query()
            ->where('status', 'closed')
            ->whereNotNull('closure_snapshot')
            ->whereNotNull('closure_fingerprint')
            ->orderBy('closed_at')
            ->get()
            ->map(fn (PlanningScenario $scenario): ?PlanningCalibrationEvaluation => $this->evaluate($scenario, $proposals, $actor))
            ->filter()
            ->values();
    }

    public function decide(PlanningCalibrationEvaluation $evaluation, User $actor, string $decision, string $note): PlanningCalibrationEvaluation
    {
        return DB::transaction(function () use ($evaluation, $actor, $decision, $note): PlanningCalibrationEvaluation {
            $locked = PlanningCalibrationEvaluation::query()->lockForUpdate()->findOrFail($evaluation->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['evaluation' => __('Only a pending calibration evaluation can be decided.')]);
            }

            $locked->update([
                'status' => 'decided',
                'decision' => $decision,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            return $locked->refresh();
        });
    }

    /** @param Collection<int, PlanningCalibrationProposal> $proposals */
    private function evaluate(PlanningScenario $scenario, Collection $proposals, User $actor): ?PlanningCalibrationEvaluation
    {
        $reference = $scenario->assumptions['calibration_reference'] ?? null;
        $proposal = $reference ? $proposals->get((int) ($reference['proposal_id'] ?? 0)) : null;
        if (! $proposal
            || ($reference['source_fingerprint'] ?? null) !== $proposal->source_fingerprint
            || PlanningCalibrationEvaluation::where('planning_scenario_id', $scenario->id)->exists()) {
            return null;
        }

        $metric = collect($scenario->closure_snapshot['areas'] ?? [])
            ->where('area', $proposal->area)
            ->flatMap(fn (array $area): array => $area['comparison'] ?? [])
            ->first(fn (array $row): bool => ($row['label'] ?? null) === $proposal->metric_label && ($row['unit'] ?? 'items') === $proposal->unit);
        $planned = (float) ($metric['planned'] ?? 0);
        if (! $metric || abs($planned) < 0.0001) {
            return null;
        }

        $observed = (float) ($metric['current'] ?? 0);
        $observedDelta = round((($observed - $planned) / abs($planned)) * 100, 3);
        $originDelta = round((float) data_get($proposal->evidence_snapshot, 'average_delta_percent', 0), 3);
        $improvement = round(abs($originDelta) - abs($observedDelta), 3);
        $recommendation = match (true) {
            abs($observedDelta) < 10 || $improvement >= 10 => 'retain',
            $improvement <= -5 => 'retire',
            default => 'revise',
        };
        $fingerprint = hash('sha256', json_encode([
            $proposal->source_fingerprint, $scenario->closure_fingerprint, $planned, $observed, $originDelta, $observedDelta,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $fingerprint, $improvement, $observed, $observedDelta, $originDelta, $planned, $proposal, $recommendation, $reference, $scenario): PlanningCalibrationEvaluation {
            if (PlanningCalibrationEvaluation::where('fingerprint', $fingerprint)->exists()) {
                return PlanningCalibrationEvaluation::where('fingerprint', $fingerprint)->firstOrFail();
            }
            $version = ((int) PlanningCalibrationEvaluation::query()
                ->where('planning_calibration_proposal_id', $proposal->id)
                ->lockForUpdate()
                ->max('version')) + 1;

            return PlanningCalibrationEvaluation::create([
                'planning_calibration_proposal_id' => $proposal->id,
                'planning_scenario_id' => $scenario->id,
                'version' => $version,
                'status' => 'pending',
                'recommendation' => $recommendation,
                'origin_delta_percent' => $originDelta,
                'observed_delta_percent' => $observedDelta,
                'improvement_points' => $improvement,
                'evidence_snapshot' => [
                    'proposal_version' => $proposal->version,
                    'proposal_source_fingerprint' => $proposal->source_fingerprint,
                    'scenario_closure_fingerprint' => $scenario->closure_fingerprint,
                    'assumption_key' => $reference['assumption_key'],
                    'previous_value' => $reference['previous_value'],
                    'applied_value' => $reference['applied_value'],
                    'metric_label' => $proposal->metric_label,
                    'unit' => $proposal->unit,
                    'planned' => round($planned, 3),
                    'observed' => round($observed, 3),
                ],
                'fingerprint' => $fingerprint,
                'created_by' => $actor->id,
            ]);
        });
    }
}
