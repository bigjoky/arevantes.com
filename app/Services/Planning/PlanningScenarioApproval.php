<?php

namespace App\Services\Planning;

use App\Models\PlanningScenario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanningScenarioApproval
{
    public function __construct(private PlanningScenarioIntegrity $integrity) {}

    public function approve(PlanningScenario $scenario, User $actor, string $expectedFingerprint, ?string $note): PlanningScenario
    {
        return DB::transaction(function () use ($scenario, $actor, $expectedFingerprint, $note): PlanningScenario {
            $locked = PlanningScenario::query()->lockForUpdate()->findOrFail($scenario->id);

            if ($locked->status !== 'frozen') {
                throw ValidationException::withMessages(['approval' => __('Only frozen planning scenarios can be approved.')]);
            }

            if (! hash_equals($locked->fingerprint, $expectedFingerprint)) {
                throw ValidationException::withMessages(['approval' => __('The scenario changed after it was opened. Refresh it before approval.')]);
            }

            if (! $this->integrity->matches($locked)) {
                throw ValidationException::withMessages(['approval' => __('The scenario fingerprint no longer matches its frozen contents.')]);
            }

            if (! $locked->reference_valid_until?->isFuture()) {
                throw ValidationException::withMessages(['approval' => __('The capacity and stock references have expired. Freeze a new scenario before approval.')]);
            }

            $locked->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approved_fingerprint' => $locked->fingerprint,
                'approval_note' => $note,
            ]);

            return $locked->refresh();
        });
    }
}
