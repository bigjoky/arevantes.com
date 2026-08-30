<?php

namespace App\Services\Planning;

use App\Models\CrmActivity;
use App\Models\PlanningScenarioRequest;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PlanningScenarioExceptionWorkflow
{
    /** @return array<string, mixed> */
    public function assignOpenTasks(PlanningScenarioRequest $request, User $actor, int $assigneeId, string $dueOn, string $note): array
    {
        abort_unless(Gate::forUser($actor)->allows('manage-installations'), 403);
        abort_unless($request->area === 'operations', 422);

        return DB::transaction(function () use ($request, $actor, $assigneeId, $dueOn, $note): array {
            $locked = PlanningScenarioRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->guardExecuted($locked, 'operational_agenda');
            $assignee = User::query()->whereKey($assigneeId)->whereIn('role', ['admin', 'commercial', 'technician'])->firstOrFail();
            $ids = CrmActivity::query()
                ->where('planning_scenario_request_id', $locked->id)
                ->whereIn('status', ['pending', 'in_progress', 'blocked'])
                ->whereNull('assigned_user_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            if ($ids->isEmpty()) {
                throw ValidationException::withMessages(['governance' => __('There are no unassigned open tasks in this planning request.')]);
            }
            $dueAt = Carbon::parse($dueOn)->endOfDay();
            CrmActivity::query()->whereKey($ids->all())->update([
                'assigned_user_id' => $assignee->id,
                'due_at' => $dueAt,
                'updated_at' => now(),
            ]);

            return [
                'kind' => 'planning_tasks_assigned', 'activity_ids' => $ids->all(),
                'assignee_id' => $assignee->id, 'due_at' => $dueAt->toIso8601String(),
                'note' => trim($note), 'acted_by' => $actor->id,
            ];
        });
    }

    /** @return array<string, mixed> */
    public function resolveExpiredReservations(PlanningScenarioRequest $request, User $actor, string $action, ?string $reservedUntil, string $note): array
    {
        abort_unless(Gate::forUser($actor)->allows('manage-catalog'), 403);
        abort_unless($request->area === 'inventory', 422);

        return DB::transaction(function () use ($request, $actor, $action, $reservedUntil, $note): array {
            $locked = PlanningScenarioRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->guardExecuted($locked, 'inventory_reservations');
            $ids = StockReservation::query()
                ->where('planning_scenario_request_id', $locked->id)
                ->where('status', 'active')
                ->whereNotNull('reserved_until')
                ->where('reserved_until', '<', now())
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            if ($ids->isEmpty()) {
                throw ValidationException::withMessages(['governance' => __('There are no expired active reservations in this planning request.')]);
            }

            $changes = $action === 'renew'
                ? ['reserved_until' => Carbon::parse((string) $reservedUntil)->endOfDay(), 'updated_at' => now()]
                : ['status' => 'released', 'updated_at' => now()];
            StockReservation::query()->whereKey($ids->all())->update($changes);

            return [
                'kind' => $action === 'renew' ? 'planning_reservations_renewed' : 'planning_reservations_released',
                'reservation_ids' => $ids->all(), 'reserved_until' => isset($changes['reserved_until']) ? $changes['reserved_until']->toIso8601String() : null,
                'note' => trim($note), 'acted_by' => $actor->id,
            ];
        });
    }

    private function guardExecuted(PlanningScenarioRequest $request, string $expectedKind): void
    {
        if ($request->execution_status !== 'executed' || data_get($request->execution_result, 'kind') !== $expectedKind) {
            throw ValidationException::withMessages(['governance' => __('Only an executed request with matching generated resources can govern exceptions.')]);
        }
    }
}
