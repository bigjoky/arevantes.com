<?php

namespace App\Services\Engineering;

use App\Models\EngineeringDossier;
use App\Models\EngineeringDossierVersion;
use App\Models\FieldServiceProfile;
use App\Models\HrEmploymentProfile;
use App\Models\HrLeaveRequest;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Work\InstallationWorkOrderSynchronizer;
use App\Services\Work\TechnicianSchedule;
use App\Services\Work\WorkOrderFactory;
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

    public function planning(EngineeringDossier $dossier, string $action, int $expectedVersion, string $fingerprint, string $note, User $actor): EngineeringDossier
    {
        abort_unless($actor->can($action === 'request' ? 'manage-installations' : 'manage-operations'), 403);

        return DB::transaction(function () use ($dossier, $action, $expectedVersion, $fingerprint, $note, $actor) {
            $locked = EngineeringDossier::query()->lockForUpdate()->findOrFail($dossier->id);
            $this->assertVersion($locked, $expectedVersion);
            $this->assertIntegrity($locked, $fingerprint);
            if (! in_array($locked->status, ['approved', 'handed_over'], true) || ! $locked->installation()->exists()) {
                throw ValidationException::withMessages(['planning' => __('Planning requires an approved technical dossier linked to a project.')]);
            }
            if ($action === 'request') {
                if ($locked->planning_status !== null) {
                    throw ValidationException::withMessages(['planning' => __('This technical dossier already has a planning request.')]);
                }
                $locked->update([
                    'planning_status' => 'pending', 'planning_fingerprint' => $fingerprint,
                    'planning_note' => $note, 'planning_requested_by' => $actor->id, 'planning_requested_at' => now(),
                ]);
            } else {
                if (! in_array($action, ['accept', 'decline'], true) || $locked->planning_status !== 'pending'
                    || ! hash_equals($locked->planning_fingerprint ?? '', $fingerprint)) {
                    throw ValidationException::withMessages(['planning' => __('Only pending technical planning requests can be reviewed.')]);
                }
                $locked->update([
                    'planning_status' => $action === 'accept' ? 'accepted' : 'declined',
                    'planning_review_note' => $note, 'planning_reviewed_by' => $actor->id, 'planning_reviewed_at' => now(),
                ]);
            }
            $this->recordEvent($locked, 'planning_'.$action, $locked->status, $locked->status, $note, $actor);

            return $locked;
        });
    }

    private function assertVersion(EngineeringDossier $dossier, int $expectedVersion): void
    {
        if ($dossier->current_version !== $expectedVersion) {
            throw ValidationException::withMessages(['expected_version' => __('The technical dossier changed after it was opened. Refresh before continuing.')]);
        }
    }

    public function availability(EngineeringDossier $dossier, array $data, User $actor): array
    {
        abort_unless($actor->can('manage-operations'), 403);
        $dossier->refresh();
        $this->assertVersion($dossier, $data['expected_version']);
        $this->assertIntegrity($dossier, $data['fingerprint']);
        $proposal = $dossier->scheduleProposals()->first();
        if ($dossier->planning_status !== 'accepted' || ! in_array($dossier->status, ['approved', 'handed_over'], true)
            || ! hash_equals($dossier->planning_fingerprint ?? '', $data['fingerprint'])
            || ! $dossier->installation()->exists()
            || ! $proposal || $proposal->id !== (int) $data['expected_proposal_id'] || $proposal->status !== 'approved' || $proposal->dossier_version !== $dossier->current_version
            || ! hash_equals($proposal->fingerprint, $data['proposal_fingerprint'])
            || ! hash_equals($proposal->fingerprint, $proposal->contentFingerprint())) {
            throw ValidationException::withMessages(['availability' => __('Refresh the approved scheduling proposal before checking availability.')]);
        }

        return app(TechnicalScheduleAvailability::class)->check($proposal, $data['start_time']);
    }

    public function schedule(EngineeringDossier $dossier, array $data, User $actor): void
    {
        abort_unless($actor->can('manage-operations'), 403);
        DB::transaction(function () use ($dossier, $data, $actor): void {
            $locked = EngineeringDossier::query()->lockForUpdate()->findOrFail($dossier->id);
            $this->assertVersion($locked, $data['expected_version']);
            $this->assertIntegrity($locked, $data['fingerprint']);
            if ($locked->planning_status !== 'accepted' || ! in_array($locked->status, ['approved', 'handed_over'], true)
                || ! hash_equals($locked->planning_fingerprint ?? '', $data['fingerprint']) || ! $locked->installation()->exists()) {
                throw ValidationException::withMessages(['schedule' => __('Only accepted technical requests can propose scheduling.')]);
            }
            $latest = $locked->scheduleProposals()->first();
            if (($latest?->id ?? 0) !== (int) $data['expected_proposal_id']) {
                throw ValidationException::withMessages(['schedule' => __('The scheduling proposal changed. Refresh before continuing.')]);
            }
            if ($data['action'] === 'propose') {
                if ($latest && $latest->status !== 'declined') {
                    throw ValidationException::withMessages(['schedule' => __('Review the current scheduling proposal before proposing another.')]);
                }
                $proposal = $locked->scheduleProposals()->create([
                    'dossier_version' => $locked->current_version, 'proposed_on' => $data['proposed_on'],
                    'assignee_id' => $data['assignee_id'], 'estimated_hours' => $data['estimated_hours'],
                    'status' => 'pending', 'note' => $data['note'], 'created_by' => $actor->id, 'fingerprint' => '',
                ]);
                $proposal->refresh();
                $proposal->update(['fingerprint' => $proposal->contentFingerprint()]);
            } else {
                if (! $latest || $latest->status !== 'pending' || ! in_array($data['action'], ['approve', 'decline'], true)
                    || ! hash_equals($latest->fingerprint, $data['proposal_fingerprint'] ?? '')
                    || ! hash_equals($latest->fingerprint, $latest->contentFingerprint())) {
                    throw ValidationException::withMessages(['schedule' => __('The scheduling proposal cannot be reviewed or failed its integrity check.')]);
                }
                if ($data['action'] === 'approve' && $latest->proposed_on->isBefore(today())) {
                    throw ValidationException::withMessages(['schedule' => __('The proposed date has passed. Decline and submit a new proposal.')]);
                }
                $latest->update(['status' => $data['action'] === 'approve' ? 'approved' : 'declined', 'reviewed_by' => $actor->id, 'review_note' => $data['note'], 'reviewed_at' => now()]);
                $proposal = $latest;
            }
            $this->recordEvent($locked, 'schedule_'.$data['action'], $locked->status, $locked->status, '#'.$proposal->id.' · '.$data['note'], $actor);
        });
    }

    public function createWorkOrder(EngineeringDossier $dossier, array $data, User $actor): WorkOrder
    {
        abort_unless($actor->can('manage-operations'), 403);

        return DB::transaction(function () use ($dossier, $data, $actor) {
            $locked = EngineeringDossier::query()->lockForUpdate()->findOrFail($dossier->id);
            $this->assertVersion($locked, $data['expected_version']);
            $this->assertIntegrity($locked, $data['fingerprint']);
            $proposal = $locked->scheduleProposals()->lockForUpdate()->first();
            if (! $proposal || $proposal->id !== (int) $data['expected_proposal_id'] || $proposal->status !== 'approved'
                || ! hash_equals($proposal->fingerprint, $data['proposal_fingerprint'])
                || ! hash_equals($proposal->fingerprint, $proposal->contentFingerprint())) {
                throw ValidationException::withMessages(['execution' => __('Refresh the approved scheduling proposal before checking availability.')]);
            }
            if ($proposal->work_order_id) {
                if ($proposal->execution_start_time !== $data['start_time']) {
                    throw ValidationException::withMessages(['execution' => __('This proposal already created a work order with a different start time.')]);
                }

                return WorkOrder::findOrFail($proposal->work_order_id);
            }
            $assignee = User::findOrFail($proposal->assignee_id);
            if ($assignee->person_id) {
                Person::whereKey($assignee->person_id)->lockForUpdate()->firstOrFail();
                HrLeaveRequest::where('person_id', $assignee->person_id)->orderBy('id')->lockForUpdate()->get(['id']);
                HrEmploymentProfile::where('person_id', $assignee->person_id)->lockForUpdate()->get(['id']);
            }
            $schedule = app(TechnicianSchedule::class);
            $schedule->lockTechnicians([$proposal->assignee_id]);
            if (User::findOrFail($proposal->assignee_id)->person_id !== $assignee->person_id) {
                throw ValidationException::withMessages(['execution' => __('Refresh the approved scheduling proposal before checking availability.')]);
            }
            FieldServiceProfile::where('user_id', $proposal->assignee_id)->lockForUpdate()->get(['id']);
            $installation = Installation::whereKey($locked->installation_id)->lockForUpdate()->firstOrFail();
            if (in_array($installation->status, ['cancelled', 'completed'], true)) {
                throw ValidationException::withMessages(['execution' => __('Reopen the project before creating a new execution order.')]);
            }
            $availability = $this->availability($locked, $data, $actor);
            if ($availability['status'] !== 'clear') {
                throw ValidationException::withMessages(['execution' => [...$availability['issues'], ...$availability['warnings']]]);
            }
            $factory = app(WorkOrderFactory::class);
            $orderData = $factory->prepare([
                'installation_id' => $installation->id, 'assigned_user_id' => $proposal->assignee_id,
                'kind' => 'installation', 'title' => $locked->code.' · '.$locked->title,
                'scheduled_start' => $availability['start'], 'scheduled_end' => $availability['end'],
                'estimated_duration_minutes' => (int) round((float) $proposal->estimated_hours * 60),
                'instructions' => $locked->scope."\n\n".$locked->proposed_solution."\n\n".$locked->risks."\n\n".$locked->standards,
                'service_zone' => $installation->site_postal_code,
            ]);
            $schedule->assertAvailable($proposal->assignee_id, $orderData['scheduled_start'], $orderData['scheduled_end'], null, $orderData['required_skills'] ?? [], $orderData['service_zone']);
            $order = $factory->create([...$orderData, 'frozen_technical_contract' => true], $actor->id);
            app(InstallationWorkOrderSynchronizer::class)->sync($order);
            $proposal->update(['work_order_id' => $order->id, 'execution_start_time' => $data['start_time'], 'executed_by' => $actor->id, 'executed_at' => now()]);
            $this->recordEvent($locked, 'work_order_created', $locked->status, $locked->status, $order->number.' · '.$data['note'], $actor);

            return $order;
        }, 3);
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
