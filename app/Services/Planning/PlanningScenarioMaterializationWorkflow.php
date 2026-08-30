<?php

namespace App\Services\Planning;

use App\Models\AccountingPeriod;
use App\Models\PlanningScenario;
use App\Models\PlanningScenarioRequest;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlanningScenarioMaterializationWorkflow
{
    public function __construct(private WorkforceCapacityForecast $capacity) {}

    public function prepare(PlanningScenarioRequest $request, User $actor, string $expectedRequestFingerprint): PlanningScenarioRequest
    {
        $this->authorizeArea($request, $actor);
        $state = $this->previewState($request);
        $idempotencyKey = (string) Str::uuid();

        return DB::transaction(function () use ($request, $actor, $expectedRequestFingerprint, $state, $idempotencyKey): PlanningScenarioRequest {
            $scenario = PlanningScenario::query()->lockForUpdate()->findOrFail($request->planning_scenario_id);
            $locked = PlanningScenarioRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->guardConfirmedRequest($locked, $scenario, $expectedRequestFingerprint);

            if ($locked->materialization_status === 'authorized') {
                throw ValidationException::withMessages(['materialization' => __('Authorized materialization previews cannot be replaced.')]);
            }

            $fingerprint = $this->previewFingerprint($locked, $idempotencyKey, $state);
            $locked->update([
                'materialization_status' => 'prepared',
                'materialization_preview' => ['captured_at' => now()->toIso8601String(), 'state' => $state, 'non_committing' => true],
                'materialization_fingerprint' => $fingerprint,
                'idempotency_key' => $idempotencyKey,
                'prepared_by' => $actor->id,
                'prepared_at' => now(),
                'authorized_by' => null,
                'authorized_at' => null,
                'authorization_note' => null,
            ]);

            return $locked->refresh();
        });
    }

    public function authorize(PlanningScenarioRequest $request, User $actor, string $expectedPreviewFingerprint, string $note): PlanningScenarioRequest
    {
        $this->authorizeArea($request, $actor);
        $currentState = $this->previewState($request);

        return DB::transaction(function () use ($request, $actor, $expectedPreviewFingerprint, $note, $currentState): PlanningScenarioRequest {
            $scenario = PlanningScenario::query()->lockForUpdate()->findOrFail($request->planning_scenario_id);
            $locked = PlanningScenarioRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->guardConfirmedRequest($locked, $scenario, $locked->request_fingerprint);

            if ($locked->materialization_status !== 'prepared' || ! $locked->materialization_fingerprint || ! $locked->idempotency_key) {
                throw ValidationException::withMessages(['authorization' => __('Only prepared materialization previews can be authorized.')]);
            }
            if (! hash_equals($locked->materialization_fingerprint, $expectedPreviewFingerprint)) {
                throw ValidationException::withMessages(['authorization' => __('The materialization preview changed after it was opened. Refresh it before authorization.')]);
            }
            $storedState = $locked->materialization_preview['state'] ?? [];
            $storedFingerprint = $this->previewFingerprint($locked, $locked->idempotency_key, $storedState);
            if (! hash_equals($locked->materialization_fingerprint, $storedFingerprint)) {
                throw ValidationException::withMessages(['authorization' => __('The materialization preview failed its integrity check.')]);
            }
            $currentFingerprint = $this->previewFingerprint($locked, $locked->idempotency_key, $currentState);
            if (! hash_equals($locked->materialization_fingerprint, $currentFingerprint)) {
                throw ValidationException::withMessages(['authorization' => __('Operational data changed after the preview. Prepare it again before authorization.')]);
            }
            if ((int) data_get($storedState, 'summary.blocking_conflicts', 0) > 0) {
                throw ValidationException::withMessages(['authorization' => __('Resolve every blocking conflict before authorization.')]);
            }
            if ($locked->confirmed_by === $actor->id) {
                throw ValidationException::withMessages(['authorization' => __('A different user must grant the second authorization.')]);
            }

            $locked->update([
                'materialization_status' => 'authorized',
                'authorized_by' => $actor->id,
                'authorized_at' => now(),
                'authorization_note' => trim($note),
            ]);

            return $locked->refresh();
        });
    }

    private function authorizeArea(PlanningScenarioRequest $request, User $actor): void
    {
        abort_unless(Gate::forUser($actor)->allows(PlanningScenarioRequestWorkflow::AREA_ABILITIES[$request->area] ?? ''), 403);
    }

    private function guardConfirmedRequest(PlanningScenarioRequest $request, PlanningScenario $scenario, string $expectedRequestFingerprint): void
    {
        if ($request->status !== 'confirmed') {
            throw ValidationException::withMessages(['materialization' => __('Confirm the area request before preparing materialization.')]);
        }
        if (! hash_equals($request->request_fingerprint, $expectedRequestFingerprint)) {
            throw ValidationException::withMessages(['materialization' => __('The area request changed after it was opened. Refresh it before materialization.')]);
        }
        $actual = hash('sha256', json_encode([$scenario->id, $request->area, $request->approved_fingerprint, $request->payload], JSON_THROW_ON_ERROR));
        if (! hash_equals($request->request_fingerprint, $actual)) {
            throw ValidationException::withMessages(['materialization' => __('The confirmed area request failed its integrity check.')]);
        }
        if ($scenario->status !== 'approved' || ! $scenario->approved_fingerprint || ! hash_equals($scenario->approved_fingerprint, $request->approved_fingerprint)) {
            throw ValidationException::withMessages(['materialization' => __('The originating scenario is no longer the approved version.')]);
        }
    }

    /** @return array<string, mixed> */
    private function previewState(PlanningScenarioRequest $request): array
    {
        return match ($request->area) {
            'operations' => $this->operationsState($request),
            'inventory' => $this->inventoryState($request),
            'purchases' => $this->purchasesState($request),
            'finance' => $this->financeState($request),
            default => throw ValidationException::withMessages(['materialization' => __('This planning area is not supported.')]),
        };
    }

    /** @return array<string, mixed> */
    private function operationsState(PlanningScenarioRequest $request): array
    {
        $start = Carbon::parse(data_get($request->payload, 'horizon.from'))->startOfWeek();
        $end = Carbon::parse(data_get($request->payload, 'horizon.to'));
        $weeks = max(1, min(12, (int) ceil(($start->diffInDays($end) + 1) / 7)));
        $forecast = $this->capacity->forecast($start, $weeks);
        $remaining = round($forecast['summary']['remaining_minutes'] / 60, 2);
        $demand = (float) data_get($request->payload, 'capacity.scenario_demand_hours', 0);
        $shortage = round(max(0, $demand - $remaining), 2);

        return $this->state(
            count($request->payload['opportunities'] ?? []),
            $shortage > 0 ? [['code' => 'capacity_shortage', 'subject' => $shortage.' h']] : [],
            ['remaining_hours' => $remaining, 'demand_hours' => $demand, 'shortage_hours' => $shortage],
        );
    }

    /** @return array<string, mixed> */
    private function inventoryState(PlanningScenarioRequest $request): array
    {
        $rows = $this->liveStock($request->payload['materials'] ?? []);
        $conflicts = $rows->where('shortage_quantity', '>', 0)->map(fn (array $row): array => [
            'code' => 'stock_shortage', 'subject' => $row['name'].' · '.$row['shortage_quantity'],
        ])->values()->all();

        return $this->state($rows->count(), $conflicts, ['materials' => $rows->all()]);
    }

    /** @return array<string, mixed> */
    private function purchasesState(PlanningScenarioRequest $request): array
    {
        $shortages = collect($request->payload['shortages'] ?? []);
        $sources = SupplierProduct::query()
            ->whereIn('product_id', $shortages->pluck('product_id'))
            ->where('is_active', true)
            ->whereHas('supplier', fn ($query) => $query->where('is_active', true))
            ->get(['id', 'product_id', 'supplier_id', 'is_preferred', 'lead_time_days', 'last_unit_price'])
            ->groupBy('product_id');
        $rows = $shortages->map(fn (array $row): array => [
            ...$row,
            'active_sources' => $sources->get($row['product_id'], collect())->count(),
        ]);
        $conflicts = $rows->where('active_sources', 0)->map(fn (array $row): array => [
            'code' => 'missing_supplier', 'subject' => $row['name'],
        ])->values()->all();

        return $this->state($rows->count(), $conflicts, ['shortages' => $rows->all()]);
    }

    /** @return array<string, mixed> */
    private function financeState(PlanningScenarioRequest $request): array
    {
        $cash = collect($request->payload['cash_by_month'] ?? []);
        $years = $cash->pluck('month')->map(fn (string $month): int => (int) substr($month, 0, 4))->unique();
        $months = $cash->pluck('month')->map(fn (string $month): int => (int) substr($month, 5, 2))->unique();
        $periods = AccountingPeriod::query()
            ->whereIn('year', $years)
            ->whereIn('month', $months)
            ->get(['year', 'month', 'status'])
            ->keyBy(fn (AccountingPeriod $period): string => sprintf('%04d-%02d', $period->year, $period->month));
        $conflicts = $cash->filter(fn (array $row): bool => $periods->get($row['month'])?->status === 'closed')
            ->map(fn (array $row): array => ['code' => 'closed_period', 'subject' => $row['month']])->values()->all();

        return $this->state($cash->count(), $conflicts, ['cash_by_month' => $cash->all()]);
    }

    /** @param array<int, array<string, mixed>> $materials */
    private function liveStock(array $materials): Collection
    {
        $rows = collect($materials);
        $productIds = $rows->pluck('product_id');
        $onHand = StockMovementLine::query()->whereIn('product_id', $productIds)->groupBy('product_id')->selectRaw('product_id, sum(quantity) quantity')->pluck('quantity', 'product_id');
        $reserved = StockReservation::query()->whereIn('product_id', $productIds)->where('status', 'active')->groupBy('product_id')
            ->selectRaw('product_id, sum(quantity - consumed_quantity) quantity')->pluck('quantity', 'product_id');

        return $rows->map(function (array $row) use ($onHand, $reserved): array {
            $free = max(0, (float) ($onHand[$row['product_id']] ?? 0) - (float) ($reserved[$row['product_id']] ?? 0));

            return [...$row, 'current_free_quantity' => round($free, 3), 'shortage_quantity' => round(max(0, (float) $row['expected_quantity'] - $free), 3)];
        });
    }

    /** @param array<int, array{code: string, subject: string}> $conflicts @param array<string, mixed> $live */
    private function state(int $items, array $conflicts, array $live): array
    {
        return [
            'summary' => ['items' => $items, 'blocking_conflicts' => count($conflicts)],
            'conflicts' => $conflicts,
            'live' => $live,
        ];
    }

    /** @param array<string, mixed> $state */
    private function previewFingerprint(PlanningScenarioRequest $request, string $idempotencyKey, array $state): string
    {
        return hash('sha256', json_encode([$request->id, $request->request_fingerprint, $idempotencyKey, $state], JSON_THROW_ON_ERROR));
    }
}
