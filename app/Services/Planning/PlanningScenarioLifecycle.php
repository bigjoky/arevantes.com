<?php

namespace App\Services\Planning;

use App\Models\PlanningScenario;
use App\Models\PlanningScenarioRequest;
use App\Models\PlanningScenarioReview;
use App\Models\User;
use App\Support\Time\BusinessTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PlanningScenarioLifecycle
{
    public function __construct(
        private PlanningScenarioIntegrity $integrity,
        private PlanningScenarioOutcomeMonitor $outcomes,
    ) {}

    public function review(PlanningScenario $scenario, User $actor, string $expectedApprovedFingerprint, string $explanation): PlanningScenarioReview
    {
        abort_unless(Gate::forUser($actor)->allows('manage-admin'), 403);

        return DB::transaction(function () use ($scenario, $actor, $expectedApprovedFingerprint, $explanation): PlanningScenarioReview {
            $locked = PlanningScenario::query()->lockForUpdate()->findOrFail($scenario->id);
            $this->guardApproved($locked, $expectedApprovedFingerprint);
            $requests = PlanningScenarioRequest::query()->where('planning_scenario_id', $locked->id)->orderBy('id')->get();
            if ($requests->isEmpty()) {
                throw ValidationException::withMessages(['review' => __('Generate the area requests before recording a scenario review.')]);
            }
            $sequence = ((int) PlanningScenarioReview::query()->where('planning_scenario_id', $locked->id)->max('sequence')) + 1;
            $snapshot = $this->snapshot($requests);
            $explanation = trim($explanation);
            $fingerprint = hash('sha256', json_encode([
                $locked->id, $locked->approved_fingerprint, $sequence, $snapshot, $explanation,
            ], JSON_THROW_ON_ERROR));

            return PlanningScenarioReview::create([
                'planning_scenario_id' => $locked->id,
                'sequence' => $sequence,
                'outcome_snapshot' => $snapshot,
                'fingerprint' => $fingerprint,
                'explanation' => $explanation,
                'created_by' => $actor->id,
                'reviewed_at' => BusinessTime::now(),
            ]);
        });
    }

    public function close(PlanningScenario $scenario, User $actor, string $expectedApprovedFingerprint, string $expectedReviewFingerprint, string $note): PlanningScenario
    {
        abort_unless(Gate::forUser($actor)->allows('manage-admin'), 403);

        return DB::transaction(function () use ($scenario, $actor, $expectedApprovedFingerprint, $expectedReviewFingerprint, $note): PlanningScenario {
            $locked = PlanningScenario::query()->lockForUpdate()->findOrFail($scenario->id);
            $this->guardApproved($locked, $expectedApprovedFingerprint);
            $latest = PlanningScenarioReview::query()->where('planning_scenario_id', $locked->id)->latest('sequence')->lockForUpdate()->first();
            if (! $latest) {
                throw ValidationException::withMessages(['closure' => __('Record at least one review before closing the scenario.')]);
            }
            if (! hash_equals($latest->fingerprint, $expectedReviewFingerprint) || ! hash_equals($latest->fingerprint, $this->reviewFingerprint($locked, $latest))) {
                throw ValidationException::withMessages(['closure' => __('The latest scenario review changed after it was opened. Refresh before closing.')]);
            }
            $requests = PlanningScenarioRequest::query()->where('planning_scenario_id', $locked->id)->orderBy('id')->get();
            if ($requests->contains(fn (PlanningScenarioRequest $request): bool => $request->execution_status !== 'executed')) {
                throw ValidationException::withMessages(['closure' => __('Execute every area request before closing the scenario.')]);
            }
            if ($requests->contains(fn (PlanningScenarioRequest $request): bool => in_array($request->area, ['purchases', 'finance'], true) && $request->promotion_status !== 'promoted')) {
                throw ValidationException::withMessages(['closure' => __('Promote purchase and finance drafts before closing the scenario.')]);
            }
            $snapshot = $this->snapshot($requests);
            if (($latest->outcome_snapshot['summary'] ?? null) !== $snapshot['summary'] || ($latest->outcome_snapshot['areas'] ?? null) !== $snapshot['areas']) {
                throw ValidationException::withMessages(['closure' => __('Scenario outcomes changed after the latest review. Record a new review before closing.')]);
            }
            $note = trim($note);
            $fingerprint = hash('sha256', json_encode([
                $locked->id, $locked->approved_fingerprint, $latest->fingerprint, $snapshot, $note,
            ], JSON_THROW_ON_ERROR));
            $locked->update([
                'status' => 'closed',
                'closure_snapshot' => $snapshot,
                'closure_fingerprint' => $fingerprint,
                'closure_note' => $note,
                'closed_by' => $actor->id,
                'closed_at' => BusinessTime::now(),
            ]);

            return $locked->refresh();
        });
    }

    private function guardApproved(PlanningScenario $scenario, string $expectedApprovedFingerprint): void
    {
        if ($scenario->status !== 'approved' || ! $scenario->approved_fingerprint) {
            throw ValidationException::withMessages(['lifecycle' => __('Only an approved open scenario can be reviewed or closed.')]);
        }
        if (! hash_equals($scenario->approved_fingerprint, $expectedApprovedFingerprint)) {
            throw ValidationException::withMessages(['lifecycle' => __('The approved scenario changed after it was opened. Refresh before continuing.')]);
        }
        if (! hash_equals($scenario->fingerprint, $scenario->approved_fingerprint) || ! $this->integrity->matches($scenario)) {
            throw ValidationException::withMessages(['lifecycle' => __('The approved scenario failed its integrity check.')]);
        }
    }

    private function reviewFingerprint(PlanningScenario $scenario, PlanningScenarioReview $review): string
    {
        return hash('sha256', json_encode([
            $scenario->id, $scenario->approved_fingerprint, $review->sequence, $review->outcome_snapshot, $review->explanation,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param Collection<int, PlanningScenarioRequest> $requests @return array<string, mixed> */
    private function snapshot(Collection $requests): array
    {
        $monitoring = $this->outcomes->forMany($requests);
        $areas = $requests->map(function (PlanningScenarioRequest $request) use ($monitoring): array {
            $outcome = $monitoring[$request->id];

            return [
                'area' => $request->area,
                'request_id' => $request->id,
                'request_status' => $request->status,
                'execution_status' => $request->execution_status,
                'promotion_status' => $request->promotion_status,
                'total' => $outcome['total'],
                'statuses' => $outcome['statuses'],
                'exceptions' => $outcome['exceptions'],
                'comparison' => $outcome['comparison'] ?? [],
            ];
        })->values();

        return [
            'captured_at' => BusinessTime::now()->toIso8601String(),
            'summary' => [
                'requests' => $requests->count(),
                'executed' => $requests->where('execution_status', 'executed')->count(),
                'resources' => (int) $areas->sum('total'),
                'pending_exceptions' => (int) $areas->sum(fn (array $area): int => (int) collect($area['exceptions'])->sum('count')),
            ],
            'areas' => $areas->all(),
        ];
    }
}
