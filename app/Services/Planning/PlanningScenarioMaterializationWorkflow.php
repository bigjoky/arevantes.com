<?php

namespace App\Services\Planning;

use App\Models\AccountingPeriod;
use App\Models\CrmActivity;
use App\Models\FinancialCategory;
use App\Models\FinancialForecastItem;
use App\Models\Opportunity;
use App\Models\PlanningScenario;
use App\Models\PlanningScenarioRequest;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Documents\DocumentNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlanningScenarioMaterializationWorkflow
{
    public function __construct(
        private WorkforceCapacityForecast $capacity,
        private DocumentNumber $numbers,
    ) {}

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

    public function execute(PlanningScenarioRequest $request, User $actor, string $expectedPreviewFingerprint, string $expectedIdempotencyKey): PlanningScenarioRequest
    {
        $this->authorizeArea($request, $actor);
        $currentState = $this->previewState($request);

        return DB::transaction(function () use ($request, $actor, $expectedPreviewFingerprint, $expectedIdempotencyKey, $currentState): PlanningScenarioRequest {
            $scenario = PlanningScenario::query()->lockForUpdate()->findOrFail($request->planning_scenario_id);
            $locked = PlanningScenarioRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->guardConfirmedRequest($locked, $scenario, $locked->request_fingerprint);

            if ($locked->materialization_status !== 'authorized' || ! $locked->materialization_fingerprint || ! $locked->idempotency_key) {
                throw ValidationException::withMessages(['execution' => __('The request requires its second authorization before execution.')]);
            }
            if ($locked->execution_status === 'executed') {
                throw ValidationException::withMessages(['execution' => __('This planning request has already been executed.')]);
            }
            if (! hash_equals($locked->materialization_fingerprint, $expectedPreviewFingerprint) || ! hash_equals($locked->idempotency_key, $expectedIdempotencyKey)) {
                throw ValidationException::withMessages(['execution' => __('The authorized execution key changed. Refresh the request before execution.')]);
            }
            $storedState = $locked->materialization_preview['state'] ?? [];
            if (! hash_equals($locked->materialization_fingerprint, $this->previewFingerprint($locked, $locked->idempotency_key, $storedState))) {
                throw ValidationException::withMessages(['execution' => __('The authorized preview failed its integrity check.')]);
            }
            if (! hash_equals($locked->materialization_fingerprint, $this->previewFingerprint($locked, $locked->idempotency_key, $currentState))) {
                throw ValidationException::withMessages(['execution' => __('Operational data changed after authorization. Prepare and authorize the request again.')]);
            }
            if ((int) data_get($currentState, 'summary.blocking_conflicts', 0) > 0) {
                throw ValidationException::withMessages(['execution' => __('Resolve every blocking conflict before execution.')]);
            }

            $result = $this->executeArea($locked, $actor, $currentState);
            $executionFingerprint = hash('sha256', json_encode([
                $locked->id, $locked->idempotency_key, $locked->materialization_fingerprint, $result,
            ], JSON_THROW_ON_ERROR));
            $locked->update([
                'execution_status' => 'executed',
                'execution_result' => $result,
                'execution_fingerprint' => $executionFingerprint,
                'executed_by' => $actor->id,
                'executed_at' => now(),
            ]);

            return $locked->refresh();
        });
    }

    public function promote(PlanningScenarioRequest $request, User $actor, string $expectedExecutionFingerprint): PlanningScenarioRequest
    {
        $this->authorizeArea($request, $actor);

        return DB::transaction(function () use ($request, $actor, $expectedExecutionFingerprint): PlanningScenarioRequest {
            $locked = PlanningScenarioRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (! in_array($locked->area, ['purchases', 'finance'], true)) {
                throw ValidationException::withMessages(['promotion' => __('Only purchase and finance drafts require native promotion.')]);
            }
            if ($locked->execution_status !== 'executed' || ! $locked->execution_fingerprint) {
                throw ValidationException::withMessages(['promotion' => __('Execute the authorized request before promoting its drafts.')]);
            }
            if (! hash_equals($locked->execution_fingerprint, $expectedExecutionFingerprint)) {
                throw ValidationException::withMessages(['promotion' => __('The executed request changed after it was opened. Refresh it before promotion.')]);
            }
            $actual = hash('sha256', json_encode([
                $locked->id, $locked->idempotency_key, $locked->materialization_fingerprint, $locked->execution_result,
            ], JSON_THROW_ON_ERROR));
            if (! hash_equals($locked->execution_fingerprint, $actual)) {
                throw ValidationException::withMessages(['promotion' => __('The executed request failed its integrity check.')]);
            }
            if ($locked->promotion_status === 'promoted') {
                return $locked;
            }

            $result = $locked->area === 'purchases'
                ? $this->promotePurchases($locked, $actor)
                : $this->promoteFinance($locked, $actor);
            $fingerprint = hash('sha256', json_encode([
                $locked->id, $locked->execution_fingerprint, $result,
            ], JSON_THROW_ON_ERROR));
            $locked->update([
                'promotion_status' => 'promoted',
                'promotion_result' => $result,
                'promotion_fingerprint' => $fingerprint,
                'promoted_by' => $actor->id,
                'promoted_at' => now(),
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

        $opportunities = collect($request->payload['opportunities'] ?? []);
        $live = Opportunity::query()->whereIn('id', $opportunities->pluck('opportunity_id'))->get(['id', 'stage'])->keyBy('id');
        $conflicts = $opportunities->filter(fn (array $row): bool => ! $live->has($row['opportunity_id']) || in_array($live[$row['opportunity_id']]->stage, ['won', 'lost'], true))
            ->map(fn (array $row): array => ['code' => 'opportunity_unavailable', 'subject' => $row['code']])->values()->all();
        if ($shortage > 0) {
            $conflicts[] = ['code' => 'capacity_shortage', 'subject' => $shortage.' h'];
        }

        return $this->state(
            count($request->payload['opportunities'] ?? []),
            $conflicts,
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
        $missingInstallations = $rows->flatMap(fn (array $row) => collect($row['allocations'] ?? [])->filter(fn (array $allocation): bool => empty($allocation['installation_id'])))
            ->map(fn (array $allocation): array => ['code' => 'missing_installation', 'subject' => (string) $allocation['opportunity_id']])->values()->all();
        $conflicts = [...$conflicts, ...$missingInstallations];
        if ($rows->isNotEmpty() && ! $rows->first()['warehouse_id']) {
            $conflicts[] = ['code' => 'missing_warehouse', 'subject' => 'default'];
        }

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
        $rows = $shortages->map(function (array $row) use ($sources): array {
            $available = $sources->get($row['product_id'], collect());
            $recommended = $available->sortBy([
                ['is_preferred', 'desc'], ['lead_time_days', 'asc'], ['last_unit_price', 'asc'], ['id', 'asc'],
            ])->first();

            return [
                ...$row,
                'active_sources' => $available->count(),
                'recommended_source' => $recommended ? [
                    'supplier_product_id' => $recommended->id,
                    'supplier_id' => $recommended->supplier_id,
                    'unit_price' => $recommended->last_unit_price,
                    'lead_time_days' => $recommended->lead_time_days,
                ] : null,
            ];
        });
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
        $warehouse = Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('id')->first(['id', 'code', 'name']);
        $onHand = StockMovementLine::query()->where('warehouse_id', $warehouse?->id)->whereIn('product_id', $productIds)->groupBy('product_id')->selectRaw('product_id, sum(quantity) quantity')->pluck('quantity', 'product_id');
        $reserved = StockReservation::query()->where('warehouse_id', $warehouse?->id)->whereIn('product_id', $productIds)->where('status', 'active')->groupBy('product_id')
            ->selectRaw('product_id, sum(quantity - consumed_quantity) quantity')->pluck('quantity', 'product_id');

        return $rows->map(function (array $row) use ($onHand, $reserved, $warehouse): array {
            $free = max(0, (float) ($onHand[$row['product_id']] ?? 0) - (float) ($reserved[$row['product_id']] ?? 0));

            return [...$row, 'warehouse_id' => $warehouse?->id, 'warehouse' => $warehouse?->code, 'current_free_quantity' => round($free, 3), 'shortage_quantity' => round(max(0, (float) $row['expected_quantity'] - $free), 3)];
        });
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function executeArea(PlanningScenarioRequest $request, User $actor, array $state): array
    {
        return match ($request->area) {
            'operations' => $this->executeOperations($request, $actor),
            'inventory' => $this->executeInventory($request, $state),
            'purchases' => ['kind' => 'purchase_drafts', 'drafts' => data_get($state, 'live.shortages', []), 'requires_purchase_approval' => true],
            'finance' => ['kind' => 'forecast_drafts', 'drafts' => data_get($state, 'live.cash_by_month', []), 'requires_finance_approval' => true],
            default => throw ValidationException::withMessages(['execution' => __('This planning area is not supported.')]),
        };
    }

    /** @return array<string, mixed> */
    private function executeOperations(PlanningScenarioRequest $request, User $actor): array
    {
        $activities = collect($request->payload['opportunities'] ?? [])->map(function (array $row) use ($request, $actor): CrmActivity {
            return CrmActivity::create([
                'planning_scenario_request_id' => $request->id,
                'installation_id' => $row['installation_id'] ?? null,
                'opportunity_id' => $row['opportunity_id'],
                'created_by' => $actor->id,
                'type' => 'task',
                'title' => __('Plan opportunity :code · :title', ['code' => $row['code'], 'title' => $row['title']]),
                'notes' => __('Generated from authorized planning request :request.', ['request' => $request->id]),
                'priority' => 'normal',
                'status' => 'pending',
                'starts_at' => data_get($request->payload, 'horizon.from'),
                'due_at' => $row['expected_close_on'] ?? data_get($request->payload, 'horizon.to'),
            ]);
        });

        return ['kind' => 'operational_agenda', 'activity_ids' => $activities->pluck('id')->all()];
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function executeInventory(PlanningScenarioRequest $request, array $state): array
    {
        $materials = collect(data_get($state, 'live.materials', []));
        Product::query()->whereIn('id', $materials->pluck('product_id'))->orderBy('id')->lockForUpdate()->get(['id']);
        $freshState = $this->inventoryState($request);
        if ((int) data_get($freshState, 'summary.blocking_conflicts', 0) > 0) {
            throw ValidationException::withMessages(['execution' => __('Inventory changed while reserving materials. Refresh the preview.')]);
        }

        $reservations = collect(data_get($freshState, 'live.materials', []))->flatMap(function (array $row) use ($request) {
            return collect($row['allocations'] ?? [])->filter(fn (array $allocation): bool => (float) $allocation['expected_quantity'] > 0)->map(fn (array $allocation): StockReservation => StockReservation::create([
                'planning_scenario_request_id' => $request->id,
                'installation_id' => $allocation['installation_id'],
                'warehouse_id' => $row['warehouse_id'],
                'product_id' => $row['product_id'],
                'quantity' => $allocation['expected_quantity'],
                'status' => 'active',
                'reserved_until' => Carbon::parse(data_get($request->payload, 'horizon.to'))->endOfDay(),
            ]));
        });

        return ['kind' => 'inventory_reservations', 'reservation_ids' => $reservations->pluck('id')->all()];
    }

    /** @return array<string, mixed> */
    private function promotePurchases(PlanningScenarioRequest $request, User $actor): array
    {
        $drafts = collect($request->execution_result['drafts'] ?? [])->filter(fn (array $row): bool => (float) ($row['shortage_quantity'] ?? 0) > 0);
        $products = Product::with('taxRate:id,rate')->whereIn('id', $drafts->pluck('product_id'))->get()->keyBy('id');
        $warehouse = Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('id')->first();
        $documents = $drafts->groupBy('recommended_source.supplier_id')->map(function (Collection $rows) use ($request, $actor, $products, $warehouse): PurchaseDocument {
            $subtotal = 0.0;
            $tax = 0.0;
            $lines = $rows->values()->map(function (array $row, int $index) use ($products, &$subtotal, &$tax): array {
                $product = $products->get($row['product_id']);
                $quantity = $product->track_serials ? ceil((float) $row['shortage_quantity']) : (float) $row['shortage_quantity'];
                $unitPrice = (float) (data_get($row, 'recommended_source.unit_price') ?? $product->cost_price);
                $taxPercent = (float) ($product->taxRate?->rate ?? 0);
                $base = round($quantity * $unitPrice, 2);
                $lineTax = round($base * $taxPercent / 100, 2);
                $subtotal += $base;
                $tax += $lineTax;

                return [
                    'product_id' => $product->id, 'position' => $index + 1, 'description' => $product->name,
                    'quantity' => $quantity, 'unit_price' => $unitPrice, 'estimated_unit_price' => $unitPrice,
                    'discount_percent' => 0, 'tax_percent' => $taxPercent, 'subtotal' => $base,
                    'tax_amount' => $lineTax, 'total' => $base + $lineTax,
                ];
            });
            $issued = $this->numbers->next('purchase_rfq', today());
            $leadTime = (int) $rows->max('recommended_source.lead_time_days');
            $document = PurchaseDocument::create([
                'planning_scenario_request_id' => $request->id,
                'type' => 'purchase_rfq',
                'supplier_id' => (int) data_get($rows->first(), 'recommended_source.supplier_id'),
                'destination_warehouse_id' => $warehouse?->id,
                'document_series_id' => $issued['series']->id,
                'created_by' => $actor->id,
                'number' => $issued['number'],
                'status' => 'draft',
                'issue_date' => today(),
                'expected_delivery_date' => $leadTime > 0 ? today()->addDays($leadTime) : null,
                'subtotal' => round($subtotal, 2), 'tax_amount' => round($tax, 2), 'shipping_cost' => 0,
                'amount' => round($subtotal + $tax, 2),
                'notes' => __('Generated from authorized planning request :request. Review and send from Purchases.', ['request' => $request->id]),
            ]);
            $document->lines()->createMany($lines->all());

            return $document;
        })->values();

        return ['kind' => 'native_purchase_rfqs', 'purchase_document_ids' => $documents->pluck('id')->all()];
    }

    /** @return array<string, mixed> */
    private function promoteFinance(PlanningScenarioRequest $request, User $actor): array
    {
        $categories = FinancialCategory::query()->whereIn('code', ['customer_collections', 'supplier_payments'])->pluck('id', 'code');
        $items = collect($request->execution_result['drafts'] ?? [])->flatMap(function (array $row) use ($request, $actor, $categories): array {
            $dueOn = Carbon::parse($row['month'].'-01')->endOfMonth()->toDateString();

            return collect([
                ['direction' => 'in', 'amount' => (float) ($row['expected_inflow'] ?? 0), 'code' => 'customer_collections', 'description' => __('Scenario :scenario expected collections', ['scenario' => data_get($request->payload, 'scenario.name')])],
                ['direction' => 'out', 'amount' => (float) ($row['expected_material_outflow'] ?? 0), 'code' => 'supplier_payments', 'description' => __('Scenario :scenario expected material payments', ['scenario' => data_get($request->payload, 'scenario.name')])],
            ])->filter(fn (array $item): bool => $item['amount'] > 0)->map(fn (array $item): array => [
                'planning_scenario_request_id' => $request->id,
                'direction' => $item['direction'], 'category' => $item['code'], 'financial_category_id' => $categories[$item['code']],
                'description' => $item['description'], 'amount' => round($item['amount'], 2), 'baseline_amount' => round($item['amount'], 2),
                'due_on' => $dueOn, 'baseline_due_on' => $dueOn, 'probability' => 100, 'status' => 'planned', 'created_by' => $actor->id,
            ])->all();
        });
        $created = $items->map(fn (array $item): FinancialForecastItem => FinancialForecastItem::create($item));

        return ['kind' => 'native_financial_forecast', 'financial_forecast_item_ids' => $created->pluck('id')->all()];
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
