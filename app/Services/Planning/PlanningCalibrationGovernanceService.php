<?php

namespace App\Services\Planning;

use App\Models\PlanningCalibrationGovernanceReview;
use App\Models\PlanningCalibrationProposal;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanningCalibrationGovernanceService
{
    public const MINIMUM_SAMPLE_SIZE = 3;

    public const CONSISTENCY_THRESHOLD = 0.75;

    /** @return Collection<int, PlanningCalibrationGovernanceReview> */
    public function generate(User $actor): Collection
    {
        return PlanningCalibrationProposal::query()
            ->where('status', 'accepted')
            ->with(['evaluations' => fn ($query) => $query->where('status', 'decided')->oldest('id')])
            ->get()
            ->map(fn (PlanningCalibrationProposal $proposal): ?PlanningCalibrationGovernanceReview => $this->review($proposal, $actor))
            ->filter()
            ->values();
    }

    public function decide(
        PlanningCalibrationGovernanceReview $review,
        User $actor,
        string $decision,
        string $note,
        ?int $replacementProposalId = null,
    ): PlanningCalibrationGovernanceReview {
        return DB::transaction(function () use ($review, $actor, $decision, $note, $replacementProposalId): PlanningCalibrationGovernanceReview {
            $locked = PlanningCalibrationGovernanceReview::query()->lockForUpdate()->findOrFail($review->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['governance' => __('Only a pending calibration governance review can be decided.')]);
            }

            $proposal = PlanningCalibrationProposal::query()->lockForUpdate()->findOrFail($locked->planning_calibration_proposal_id);
            if (in_array($proposal->governance_state, ['superseded', 'insufficient'], true)) {
                throw ValidationException::withMessages(['governance' => __('This governance review is stale because the calibration version is no longer available.')]);
            }
            $replacement = $decision === 'supersede'
                ? $this->replacement($proposal, $replacementProposalId)
                : null;

            if ($decision === 'retain') {
                $this->activate($proposal);
            } elseif ($decision === 'supersede') {
                $this->activate($replacement);
            } else {
                $proposal->update(['governance_state' => 'insufficient', 'superseded_by_id' => null]);
            }

            $locked->update([
                'status' => 'decided',
                'decision' => $decision,
                'replacement_proposal_id' => $replacement?->id,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            return $locked->refresh();
        });
    }

    private function review(PlanningCalibrationProposal $proposal, User $actor): ?PlanningCalibrationGovernanceReview
    {
        $evaluations = $proposal->evaluations;
        if ($evaluations->count() < self::MINIMUM_SAMPLE_SIZE) {
            return null;
        }

        $fingerprint = hash('sha256', json_encode([
            $proposal->source_fingerprint,
            $evaluations->map(fn ($evaluation): array => [$evaluation->fingerprint, $evaluation->decision])->all(),
        ], JSON_THROW_ON_ERROR));
        if (PlanningCalibrationGovernanceReview::where('fingerprint', $fingerprint)->exists()) {
            return null;
        }

        $counts = collect(['retain', 'revise', 'retire'])->mapWithKeys(
            fn (string $decision): array => [$decision => $evaluations->where('decision', $decision)->count()],
        );
        $largest = (int) $counts->max();
        $consistency = round($largest / $evaluations->count(), 3);
        $recommendation = match (true) {
            $consistency < self::CONSISTENCY_THRESHOLD => 'insufficient',
            $counts['retain'] === $largest => 'retain',
            $counts['retire'] === $largest => 'retire',
            default => 'insufficient',
        };

        return DB::transaction(function () use ($actor, $consistency, $counts, $evaluations, $fingerprint, $proposal, $recommendation): ?PlanningCalibrationGovernanceReview {
            PlanningCalibrationProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if (PlanningCalibrationGovernanceReview::where('fingerprint', $fingerprint)->exists()) {
                return null;
            }
            $version = ((int) PlanningCalibrationGovernanceReview::query()
                ->where('planning_calibration_proposal_id', $proposal->id)
                ->max('version')) + 1;

            return PlanningCalibrationGovernanceReview::create([
                'planning_calibration_proposal_id' => $proposal->id,
                'version' => $version,
                'status' => 'pending',
                'recommendation' => $recommendation,
                'sample_size' => $evaluations->count(),
                'retain_count' => $counts['retain'],
                'revise_count' => $counts['revise'],
                'retire_count' => $counts['retire'],
                'consistency' => $consistency,
                'evidence_snapshot' => [
                    'minimum_sample_size' => self::MINIMUM_SAMPLE_SIZE,
                    'consistency_threshold' => self::CONSISTENCY_THRESHOLD,
                    'proposal_version' => $proposal->version,
                    'proposal_source_fingerprint' => $proposal->source_fingerprint,
                    'evaluations' => $evaluations->map(fn ($evaluation): array => [
                        'evaluation_id' => $evaluation->id,
                        'evaluation_version' => $evaluation->version,
                        'fingerprint' => $evaluation->fingerprint,
                        'decision' => $evaluation->decision,
                        'scenario_id' => $evaluation->planning_scenario_id,
                    ])->all(),
                ],
                'fingerprint' => $fingerprint,
                'created_by' => $actor->id,
            ]);
        });
    }

    private function replacement(PlanningCalibrationProposal $proposal, ?int $replacementProposalId): PlanningCalibrationProposal
    {
        $replacement = $replacementProposalId
            ? PlanningCalibrationProposal::query()->lockForUpdate()->find($replacementProposalId)
            : null;
        if (! $replacement
            || $replacement->id === $proposal->id
            || $replacement->status !== 'accepted'
            || $replacement->business_line !== $proposal->business_line
            || $replacement->metric_key !== $proposal->metric_key
            || $replacement->version <= $proposal->version
            || in_array($replacement->governance_state, ['superseded', 'insufficient'], true)) {
            throw ValidationException::withMessages(['replacement_proposal_id' => __('Select a newer accepted calibration version from the same evidence series.')]);
        }

        return $replacement;
    }

    private function activate(PlanningCalibrationProposal $proposal): void
    {
        PlanningCalibrationProposal::query()
            ->where('status', 'accepted')
            ->where('business_line', $proposal->business_line)
            ->where('metric_key', $proposal->metric_key)
            ->whereKeyNot($proposal->id)
            ->where(function ($query) use ($proposal): void {
                $query->where('governance_state', 'active')
                    ->orWhere('version', '<', $proposal->version);
            })
            ->update(['governance_state' => 'superseded', 'superseded_by_id' => $proposal->id]);

        $proposal->update(['governance_state' => 'active', 'superseded_by_id' => null]);
    }
}
