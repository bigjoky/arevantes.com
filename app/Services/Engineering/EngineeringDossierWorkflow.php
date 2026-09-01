<?php

namespace App\Services\Engineering;

use App\Models\EngineeringDossier;
use App\Models\EngineeringDossierVersion;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EngineeringDossierWorkflow
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): EngineeringDossier
    {
        return DB::transaction(function () use ($data, $actor): EngineeringDossier {
            $this->validateTargets($data);
            $dossier = EngineeringDossier::create([
                ...$data,
                'code' => 'TMP-'.str()->uuid(),
                'status' => 'draft',
                'current_version' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $dossier->update(['code' => 'ENG-'.now()->format('Y').'-'.str_pad((string) $dossier->id, 6, '0', STR_PAD_LEFT)]);
            $this->recordVersion($dossier, __('Initial technical dossier created.'), $actor);
            $this->recordEvent($dossier, 'created', null, 'draft', __('Technical dossier created.'), $actor);

            return $dossier;
        });
    }

    /** @param array<string, mixed> $data */
    public function revise(EngineeringDossier $dossier, array $data, int $expectedVersion, string $changeNote, User $actor): EngineeringDossier
    {
        return DB::transaction(function () use ($dossier, $data, $expectedVersion, $changeNote, $actor): EngineeringDossier {
            $locked = EngineeringDossier::query()->lockForUpdate()->findOrFail($dossier->id);
            $this->assertVersion($locked, $expectedVersion);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['status' => __('Only draft technical dossiers can be edited.')]);
            }
            $this->validateTargets($data);
            $locked->update([...$data, 'current_version' => $locked->current_version + 1, 'updated_by' => $actor->id]);
            $this->recordVersion($locked, $changeNote, $actor);
            $this->recordEvent($locked, 'revised', 'draft', 'draft', $changeNote, $actor);

            return $locked;
        });
    }

    public function transition(EngineeringDossier $dossier, string $action, int $expectedVersion, ?string $expectedFingerprint, string $note, User $actor): EngineeringDossier
    {
        return DB::transaction(function () use ($dossier, $action, $expectedVersion, $expectedFingerprint, $note, $actor): EngineeringDossier {
            $locked = EngineeringDossier::query()->lockForUpdate()->findOrFail($dossier->id);
            $this->assertVersion($locked, $expectedVersion);
            [$from, $to] = match ($action) {
                'submit' => ['draft', 'in_review'],
                'return' => ['in_review', 'draft'],
                'approve' => ['in_review', 'approved'],
                'handover' => ['approved', 'handed_over'],
                default => throw ValidationException::withMessages(['action' => __('This technical dossier transition is not valid.')]),
            };
            if ($locked->status !== $from) {
                throw ValidationException::withMessages(['status' => __('This technical dossier transition is not valid.')]);
            }
            if ($action === 'submit' && blank($locked->proposed_solution)) {
                throw ValidationException::withMessages(['proposed_solution' => __('Define the proposed solution before review.')]);
            }
            if (in_array($action, ['approve', 'handover'], true)) {
                $this->assertIntegrity($locked, $expectedFingerprint);
            }
            if ($action === 'handover' && ! $locked->installation_id) {
                throw ValidationException::withMessages(['installation_id' => __('Link a project before handing the dossier to execution.')]);
            }

            $attributes = ['status' => $to, 'updated_by' => $actor->id];
            if ($action === 'approve') {
                $attributes += ['approved_by' => $actor->id, 'approved_at' => now()];
            }
            if ($action === 'handover') {
                $attributes += ['handed_over_by' => $actor->id, 'handed_over_at' => now()];
            }
            $locked->update($attributes);
            $this->recordEvent($locked, $action, $from, $to, $note, $actor);

            return $locked;
        });
    }

    /** @param array<string, mixed> $data */
    private function validateTargets(array $data): void
    {
        if (blank($data['opportunity_id'] ?? null) && blank($data['installation_id'] ?? null)) {
            throw ValidationException::withMessages(['installation_id' => __('Link an opportunity or project to the technical dossier.')]);
        }
        if (filled($data['opportunity_id'] ?? null) && filled($data['installation_id'] ?? null)) {
            $opportunityInstallation = Opportunity::withTrashed()->whereKey($data['opportunity_id'])->value('installation_id');
            if ($opportunityInstallation && (int) $opportunityInstallation !== (int) $data['installation_id']) {
                throw ValidationException::withMessages(['installation_id' => __('The opportunity belongs to a different project.')]);
            }
        }
    }

    private function assertVersion(EngineeringDossier $dossier, int $expectedVersion): void
    {
        if ($dossier->current_version !== $expectedVersion) {
            throw ValidationException::withMessages(['expected_version' => __('The technical dossier changed after it was opened. Refresh before continuing.')]);
        }
    }

    private function assertIntegrity(EngineeringDossier $dossier, ?string $expectedFingerprint): void
    {
        $version = $dossier->versions()->where('version', $dossier->current_version)->firstOrFail();
        $currentFingerprint = hash('sha256', json_encode($this->snapshot($dossier), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        if (! $expectedFingerprint
            || ! hash_equals($version->fingerprint, $expectedFingerprint)
            || ! hash_equals($version->fingerprint, $currentFingerprint)) {
            throw ValidationException::withMessages(['expected_fingerprint' => __('The technical dossier failed its integrity check.')]);
        }
    }

    private function recordVersion(EngineeringDossier $dossier, string $changeNote, User $actor): EngineeringDossierVersion
    {
        $snapshot = $this->snapshot($dossier);

        return $dossier->versions()->create([
            'version' => $dossier->current_version,
            'snapshot' => $snapshot,
            'fingerprint' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
            'change_note' => $changeNote,
            'created_by' => $actor->id,
        ]);
    }

    private function recordEvent(EngineeringDossier $dossier, string $type, ?string $from, ?string $to, string $note, User $actor): void
    {
        $dossier->events()->create([
            'actor_id' => $actor->id,
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'dossier_version' => $dossier->current_version,
            'note' => $note,
            'occurred_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(EngineeringDossier $dossier): array
    {
        return $dossier->only([
            'code', 'title', 'business_line', 'kind', 'opportunity_id', 'installation_id', 'scope', 'site_conditions',
            'calculations', 'proposed_solution', 'risks', 'standards', 'conclusions',
        ]);
    }
}
