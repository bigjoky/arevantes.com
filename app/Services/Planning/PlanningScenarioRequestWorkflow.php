<?php

namespace App\Services\Planning;

use App\Models\PlanningScenario;
use App\Models\PlanningScenarioRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PlanningScenarioRequestWorkflow
{
    public const AREA_ABILITIES = [
        'operations' => 'manage-operations',
        'inventory' => 'manage-catalog',
        'purchases' => 'manage-purchases',
        'finance' => 'manage-treasury',
    ];

    public function __construct(private PlanningScenarioIntegrity $integrity) {}

    /** @return Collection<int, PlanningScenarioRequest> */
    public function generate(PlanningScenario $scenario, User $actor, string $expectedFingerprint): Collection
    {
        return DB::transaction(function () use ($scenario, $actor, $expectedFingerprint): Collection {
            $locked = PlanningScenario::query()->lockForUpdate()->findOrFail($scenario->id);

            if ($locked->status !== 'approved' || ! $locked->approved_fingerprint) {
                throw ValidationException::withMessages(['requests' => __('Only an approved planning scenario can create area requests.')]);
            }
            if (! hash_equals($locked->approved_fingerprint, $expectedFingerprint)) {
                throw ValidationException::withMessages(['requests' => __('The approved scenario changed after it was opened. Refresh it before continuing.')]);
            }
            if (! hash_equals($locked->fingerprint, $locked->approved_fingerprint) || ! $this->integrity->matches($locked)) {
                throw ValidationException::withMessages(['requests' => __('The approved scenario failed its integrity check.')]);
            }
            if (! $locked->reference_valid_until?->isFuture()) {
                throw ValidationException::withMessages(['requests' => __('The approved scenario references have expired. Freeze and approve a new scenario.')]);
            }
            if ($locked->actionRequests()->exists()) {
                throw ValidationException::withMessages(['requests' => __('Area requests have already been generated for this scenario.')]);
            }

            return collect($this->payloads($locked))->map(function (array $payload, string $area) use ($locked, $actor): PlanningScenarioRequest {
                $fingerprint = $this->requestFingerprint($locked->id, $area, $locked->approved_fingerprint, $payload);

                return $locked->actionRequests()->create([
                    'area' => $area,
                    'status' => 'draft',
                    'approved_fingerprint' => $locked->approved_fingerprint,
                    'request_fingerprint' => $fingerprint,
                    'payload' => $payload,
                    'generated_by' => $actor->id,
                ]);
            })->values();
        });
    }

    public function confirm(PlanningScenarioRequest $request, User $actor, string $expectedFingerprint, string $note): PlanningScenarioRequest
    {
        abort_unless(Gate::forUser($actor)->allows(self::AREA_ABILITIES[$request->area] ?? ''), 403);

        return DB::transaction(function () use ($request, $actor, $expectedFingerprint, $note): PlanningScenarioRequest {
            $scenario = PlanningScenario::query()->lockForUpdate()->findOrFail($request->planning_scenario_id);
            $locked = PlanningScenarioRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['confirmation' => __('Only draft area requests can be confirmed.')]);
            }
            if (! hash_equals($locked->request_fingerprint, $expectedFingerprint)) {
                throw ValidationException::withMessages(['confirmation' => __('The area request changed after it was opened. Refresh it before confirmation.')]);
            }
            $actual = $this->requestFingerprint($scenario->id, $locked->area, $locked->approved_fingerprint, $locked->payload);
            if (! hash_equals($locked->request_fingerprint, $actual)) {
                throw ValidationException::withMessages(['confirmation' => __('The area request failed its integrity check.')]);
            }
            if ($scenario->status !== 'approved' || ! $scenario->approved_fingerprint || ! hash_equals($scenario->approved_fingerprint, $locked->approved_fingerprint)) {
                throw ValidationException::withMessages(['confirmation' => __('The originating scenario is no longer the approved version.')]);
            }

            $locked->update([
                'status' => 'confirmed',
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'confirmation_note' => trim($note),
            ]);

            return $locked->refresh();
        });
    }

    /** @return array<int, string> */
    public function areasFor(User $user): array
    {
        return collect(self::AREA_ABILITIES)
            ->filter(fn (string $ability): bool => Gate::forUser($user)->allows($ability))
            ->keys()
            ->all();
    }

    /** @return array<string, array<string, mixed>> */
    private function payloads(PlanningScenario $scenario): array
    {
        $common = [
            'scenario' => ['id' => $scenario->id, 'name' => $scenario->name, 'version' => $scenario->version],
            'horizon' => ['from' => $scenario->horizon_starts_on->toDateString(), 'to' => $scenario->horizon_ends_on->toDateString()],
            'non_committing' => true,
        ];
        $results = $scenario->result_snapshot;
        $references = $scenario->reference_snapshot;

        return [
            'operations' => $common + [
                'capacity' => $references['capacity'],
                'opportunities' => collect($scenario->opportunity_snapshot['opportunities'])->map(fn (array $row): array => [
                    'opportunity_id' => $row['opportunity_id'],
                    'code' => $row['confirmed']['code'],
                    'title' => $row['confirmed']['title'],
                    'business_line' => $row['confirmed']['business_line'],
                    'installation_id' => $row['confirmed']['installation_id'] ?? null,
                    'expected_close_on' => $row['confirmed']['expected_close_on'],
                    'capacity_hours' => $row['projected']['capacity_hours'],
                ])->all(),
            ],
            'inventory' => $common + ['materials' => $references['stock']['products']],
            'purchases' => $common + [
                'expected_material_cost' => $results['summary']['expected_material_cost'],
                'shortages' => collect($references['stock']['products'])->where('shortage_quantity', '>', 0)->values()->all(),
            ],
            'finance' => $common + [
                'summary' => $results['summary'],
                'cash_by_month' => $results['cash_by_month'],
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function requestFingerprint(int $scenarioId, string $area, string $approvedFingerprint, array $payload): string
    {
        return hash('sha256', json_encode([$scenarioId, $area, $approvedFingerprint, $payload], JSON_THROW_ON_ERROR));
    }
}
