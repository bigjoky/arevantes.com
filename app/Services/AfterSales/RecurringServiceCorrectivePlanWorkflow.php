<?php

namespace App\Services\AfterSales;

use App\Models\ServiceCorrectiveAction;
use App\Models\ServiceCorrectivePlan;
use App\Models\ServiceCorrectiveProposal;
use App\Models\ServiceCycleDeviation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringServiceCorrectivePlanWorkflow
{
    public function __construct(private RecurringServiceEffectivenessEvaluator $effectiveness) {}

    /** @param list<int> $deviationIds @param array<string, mixed> $attributes @param array<string, mixed> $firstAction */
    public function create(ServiceCycleDeviation $primary, array $deviationIds, array $attributes, array $firstAction, User $actor): ServiceCorrectivePlan
    {
        return DB::transaction(function () use ($primary, $deviationIds, $attributes, $firstAction, $actor): ServiceCorrectivePlan {
            $deviations = ServiceCycleDeviation::query()->with('commitment:id,maintenance_plan_id,maintenance_plan_version,planned_minutes,completed_at')->whereIn('id', $deviationIds)->lockForUpdate()->get();
            if (! $deviations->contains('id', $primary->id)) {
                throw ValidationException::withMessages(['deviation_ids' => __('The originating deviation must remain in the corrective plan.')]);
            }
            $maintenancePlanId = $primary->commitment()->value('maintenance_plan_id');
            if ($deviations->count() !== count(array_unique($deviationIds)) || $deviations->contains(fn (ServiceCycleDeviation $deviation) => ! in_array($deviation->status, ['confirmed', 'resolved'], true) || $deviation->commitment->maintenance_plan_id !== $maintenancePlanId)) {
                throw ValidationException::withMessages(['deviation_ids' => __('Corrective plans can only group confirmed deviations from the same maintenance plan.')]);
            }

            $baseline = $this->baseline($deviations);
            $plan = ServiceCorrectivePlan::create([
                ...$attributes, 'maintenance_plan_id' => $maintenancePlanId, 'status' => 'draft',
                'baseline_snapshot' => $baseline, 'created_by' => $actor->id,
            ]);
            $plan->updateQuietly(['code' => 'CAP-'.now()->format('Y').'-'.str_pad((string) $plan->id, 6, '0', STR_PAD_LEFT)]);
            $plan->deviations()->sync($deviations->pluck('id'));
            $plan->actions()->create([...$firstAction, 'status' => 'open']);
            $this->event($plan, 'created', null, 'draft', __('Corrective plan created from confirmed service deviations.'), ['deviation_ids' => $deviations->pluck('id')->all()], $actor);

            return $plan->refresh();
        });
    }

    public function transition(ServiceCorrectivePlan $plan, string $action, string $note, User $actor): ServiceCorrectivePlan
    {
        return DB::transaction(function () use ($plan, $action, $note, $actor): ServiceCorrectivePlan {
            $locked = ServiceCorrectivePlan::query()->lockForUpdate()->findOrFail($plan->id);
            $from = $locked->status;
            $allowed = [
                'activate' => ['draft'], 'start_verification' => ['in_progress'],
                'verify_effective' => ['verification'], 'verify_ineffective' => ['verification'],
                'close' => ['effective', 'ineffective'], 'reopen' => ['effective', 'ineffective', 'closed'],
            ];
            if (! in_array($from, $allowed[$action] ?? [], true)) {
                throw ValidationException::withMessages(['action' => __('This corrective-plan transition is not valid for the current status.')]);
            }

            $changes = [];
            $to = match ($action) {
                'activate', 'reopen' => 'in_progress',
                'start_verification' => 'verification',
                'verify_effective' => 'effective',
                'verify_ineffective' => 'ineffective',
                'close' => 'closed',
            };
            if ($action === 'activate') {
                if (! $locked->actions()->exists()) {
                    throw ValidationException::withMessages(['action' => __('Add at least one corrective or preventive action before activation.')]);
                }
                $changes['started_at'] = now();
            } elseif ($action === 'start_verification') {
                if ($locked->actions()->whereNotIn('status', ['completed', 'cancelled'])->exists() || ! $locked->actions()->where('status', 'completed')->exists()) {
                    throw ValidationException::withMessages(['action' => __('Complete the planned actions before starting effectiveness verification.')]);
                }
                $changes['verification_started_at'] = now();
            } elseif (str_starts_with($action, 'verify_')) {
                $snapshot = $this->effectiveness->evaluate($locked);
                if (! $snapshot['ready']) {
                    throw ValidationException::withMessages(['action' => __('Complete :count more service cycles before verifying effectiveness.', ['count' => $snapshot['remaining_cycles']])]);
                }
                $result = $action === 'verify_effective' ? 'effective' : 'ineffective';
                $changes += ['effectiveness_snapshot' => $snapshot, 'effectiveness_result' => $result, 'effectiveness_note' => trim($note), 'verified_by' => $actor->id, 'verified_at' => now()];
            } elseif ($action === 'reopen') {
                $changes += ['effectiveness_snapshot' => null, 'effectiveness_result' => null, 'effectiveness_note' => null, 'verified_by' => null, 'verified_at' => null, 'verification_started_at' => null];
            }
            $changes['status'] = $to;
            $locked->update($changes);
            $this->event($locked, $action, $from, $to, $note, $changes, $actor);

            return $locked->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function addAction(ServiceCorrectivePlan $plan, array $attributes, User $actor): ServiceCorrectiveAction
    {
        if (! in_array($plan->status, ['draft', 'in_progress'], true)) {
            throw ValidationException::withMessages(['action' => __('Actions can only be added while the corrective plan is being prepared or executed.')]);
        }
        $action = $plan->actions()->create([...$attributes, 'status' => 'open']);
        $this->event($plan, 'action_added', $plan->status, $plan->status, $action->title, ['action_id' => $action->id], $actor);

        return $action;
    }

    public function updateAction(ServiceCorrectiveAction $action, string $status, ?string $completionNote, User $actor): ServiceCorrectiveAction
    {
        return DB::transaction(function () use ($action, $status, $completionNote, $actor): ServiceCorrectiveAction {
            $locked = ServiceCorrectiveAction::query()->with('plan')->lockForUpdate()->findOrFail($action->id);
            if (! in_array($locked->plan->status, ['draft', 'in_progress'], true)) {
                throw ValidationException::withMessages(['status' => __('This action is locked while the plan is under verification or closed.')]);
            }
            $allowed = ['open' => ['in_progress', 'completed', 'cancelled'], 'in_progress' => ['open', 'completed', 'cancelled'], 'completed' => ['in_progress'], 'cancelled' => ['open']];
            if (! in_array($status, $allowed[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => __('This corrective-action transition is not valid.')]);
            }
            if ($status === 'completed' && blank($completionNote)) {
                throw ValidationException::withMessages(['completion_note' => __('Explain the evidence that demonstrates completion.')]);
            }
            $from = $locked->status;
            $locked->update([
                'status' => $status, 'completion_note' => $status === 'completed' ? trim((string) $completionNote) : null,
                'completed_by' => $status === 'completed' ? $actor->id : null, 'completed_at' => $status === 'completed' ? now() : null,
            ]);
            $this->event($locked->plan, 'action_'.$status, $locked->plan->status, $locked->plan->status, $completionNote ?: $locked->title, ['action_id' => $locked->id, 'from_status' => $from], $actor);

            return $locked->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function addProposal(ServiceCorrectivePlan $plan, array $attributes, User $actor): ServiceCorrectiveProposal
    {
        if (in_array($plan->status, ['effective', 'ineffective', 'closed'], true)) {
            throw ValidationException::withMessages(['proposal' => __('Closed corrective plans cannot prepare new change proposals.')]);
        }
        $proposal = $plan->proposals()->create([...$attributes, 'status' => 'prepared', 'created_by' => $actor->id]);
        $this->event($plan, 'proposal_prepared', $plan->status, $plan->status, $proposal->title, ['proposal_id' => $proposal->id, 'type' => $proposal->type], $actor);

        return $proposal;
    }

    /** @param Collection<int, ServiceCycleDeviation> $deviations @return array<string, mixed> */
    private function baseline(Collection $deviations): array
    {
        $cycles = $deviations->pluck('commitment')->unique('id')->values();
        $categories = $deviations->pluck('category')->unique()->values();
        $opportunities = max(1, $cycles->count() * max(1, $categories->count()));

        return [
            'deviation_ids' => $deviations->pluck('id')->all(), 'categories' => $categories->all(),
            'deviation_count' => $deviations->count(), 'opportunities' => $opportunities,
            'deviation_rate' => round($deviations->count() / $opportunities, 4),
            'mean_excess_percent' => round((float) $deviations->avg(fn ($deviation) => max(0, abs((float) $deviation->variance_percent) - (float) $deviation->tolerance_percent)), 2),
            'cutoff_completed_at' => $cycles->max('completed_at')->toISOString(),
            'volume' => [
                'cycles' => $cycles->count(), 'categories' => $categories->count(),
                'planned_minutes_per_cycle' => round((float) $cycles->avg('planned_minutes'), 2),
                'maintenance_plan_versions' => $cycles->pluck('maintenance_plan_version')->unique()->sort()->values()->all(),
            ],
        ];
    }

    /** @param array<string, mixed> $changes */
    private function event(ServiceCorrectivePlan $plan, string $event, ?string $from, ?string $to, ?string $note, array $changes, User $actor): void
    {
        $plan->events()->create(['event' => $event, 'from_status' => $from, 'to_status' => $to, 'note' => trim((string) $note), 'changes' => $changes, 'actor_id' => $actor->id, 'created_at' => now()]);
    }
}
