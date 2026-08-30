<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\FinancialForecastItem;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\PlanningScenario;
use App\Models\PlanningScenarioRequest;
use App\Models\Product;
use App\Models\Quote;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Planning\PlanningScenarioIntegrity;
use App\Services\Planning\PlanningScenarioOutcomeMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlanningScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_executed_purchase_and_finance_drafts_are_promoted_once_and_remain_traceable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $scenario = PlanningScenario::create([
            'name' => 'Native workflow', 'version' => 1, 'status' => 'approved',
            'horizon_starts_on' => '2026-09-01', 'horizon_ends_on' => '2026-12-31',
            'assumptions' => [], 'opportunity_snapshot' => ['opportunities' => []],
            'result_snapshot' => ['summary' => [], 'cash_by_month' => []],
            'fingerprint' => str_repeat('a', 64), 'approved_fingerprint' => str_repeat('a', 64),
            'created_by' => $admin->id, 'approved_by' => $admin->id, 'approved_at' => now(),
        ]);
        $product = Product::create([
            'type' => 'product', 'sku' => 'PLAN-NATIVE', 'name' => 'Planned panel',
            'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 200, 'cost_price' => 100, 'track_stock' => true, 'is_active' => true,
        ]);
        $supplier = Supplier::create(['legal_name' => 'Planning Supplier SL', 'country' => 'ES', 'is_active' => true]);
        $source = SupplierProduct::create([
            'supplier_id' => $supplier->id, 'product_id' => $product->id, 'last_unit_price' => 90,
            'lead_time_days' => 5, 'is_preferred' => true, 'is_active' => true,
        ]);
        $warehouse = Warehouse::create(['code' => 'NATIVE', 'name' => 'Native stock', 'is_default' => true, 'is_active' => true]);
        $purchaseResult = ['kind' => 'purchase_drafts', 'drafts' => [[
            'product_id' => $product->id, 'name' => $product->name, 'shortage_quantity' => 2,
            'recommended_source' => ['supplier_product_id' => $source->id, 'supplier_id' => $supplier->id, 'unit_price' => 90, 'lead_time_days' => 5],
        ]], 'requires_purchase_approval' => true];
        $financeResult = ['kind' => 'forecast_drafts', 'drafts' => [[
            'month' => '2026-10', 'expected_inflow' => 1000, 'expected_material_outflow' => 180,
        ]], 'requires_finance_approval' => true];

        $purchaseRequest = $this->executedRequest($scenario, $admin, 'purchases', $purchaseResult);
        $financeRequest = $this->executedRequest($scenario, $admin, 'finance', $financeResult);
        $this->actingAs($admin)->post(route('planning-scenario-requests.promotion.store', $purchaseRequest), [
            'fingerprint' => $purchaseRequest->execution_fingerprint,
        ])->assertRedirect();
        $this->post(route('planning-scenario-requests.promotion.store', $purchaseRequest), [
            'fingerprint' => $purchaseRequest->execution_fingerprint,
        ])->assertRedirect();
        $this->post(route('planning-scenario-requests.promotion.store', $financeRequest), [
            'fingerprint' => $financeRequest->execution_fingerprint,
        ])->assertRedirect();

        $operationsRequest = $this->executedRequest($scenario, $admin, 'operations', ['kind' => 'operational_agenda', 'activity_ids' => []]);
        $activity = CrmActivity::create([
            'planning_scenario_request_id' => $operationsRequest->id, 'created_by' => $admin->id,
            'type' => 'task', 'title' => 'Planning exception', 'status' => 'pending', 'due_at' => now()->subDay(),
        ]);
        $unrelated = CrmActivity::create(['created_by' => $admin->id, 'type' => 'task', 'title' => 'Unrelated task', 'status' => 'pending']);
        $installation = Installation::create(['service_type' => 'security', 'status' => 'scheduled', 'project_reference' => 'PLAN-NATIVE', 'site_address_line_1' => 'Planning 1']);
        $inventoryRequest = $this->executedRequest($scenario, $admin, 'inventory', ['kind' => 'inventory_reservations', 'reservation_ids' => []]);
        $reservation = StockReservation::create([
            'planning_scenario_request_id' => $inventoryRequest->id, 'installation_id' => $installation->id,
            'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 2,
            'consumed_quantity' => 0, 'status' => 'active', 'reserved_until' => now()->subDay(),
        ]);

        $this->assertDatabaseCount('purchase_documents', 1);
        $this->assertDatabaseHas('purchase_documents', [
            'planning_scenario_request_id' => $purchaseRequest->id, 'supplier_id' => $supplier->id,
            'type' => 'purchase_rfq', 'status' => 'draft', 'amount' => 217.80,
        ]);
        $this->assertDatabaseCount('financial_forecast_items', 2);
        $this->assertSame(['in', 'out'], FinancialForecastItem::where('planning_scenario_request_id', $financeRequest->id)->orderBy('direction')->pluck('direction')->sort()->values()->all());
        $this->assertSame('promoted', $purchaseRequest->fresh()->promotion_status);
        $this->assertSame('promoted', $financeRequest->fresh()->promotion_status);
        $this->assertCount(1, $purchaseRequest->fresh()->promotion_result['purchase_document_ids']);
        $this->assertCount(2, $financeRequest->fresh()->promotion_result['financial_forecast_item_ids']);
        $this->get(route('purchases.index', ['planning_request' => $purchaseRequest->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('documents.total', 1)
                ->where('documents.data.0.planning_scenario_request_id', $purchaseRequest->id)
                ->where('filters.planning_request', $purchaseRequest->id));
        $this->get(route('finance.index', ['planning_request' => $financeRequest->id, 'year' => 2026]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('forecastItems', 2)
                ->where('forecastItems.0.planning_scenario_request_id', $financeRequest->id)
                ->where('planningRequestId', $financeRequest->id));
        $this->get(route('operations.index', ['planning_request' => $operationsRequest->id, 'status' => 'all', 'view' => 'today']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activities.total', 1)
                ->where('activities.data.0.id', $activity->id)
                ->where('filters.planning_request', $operationsRequest->id));
        $this->get(route('inventory.index', ['planning_request' => $inventoryRequest->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('reservations', 1)
                ->where('reservations.0.id', $reservation->id)
                ->where('planningRequestId', $inventoryRequest->id));
        $monitoring = app(PlanningScenarioOutcomeMonitor::class)->forMany(collect([$operationsRequest, $inventoryRequest, $purchaseRequest, $financeRequest]));
        $this->assertSame('Unassigned tasks', $monitoring[$operationsRequest->id]['exceptions'][0]['label']);
        $this->assertSame('Overdue tasks', $monitoring[$operationsRequest->id]['exceptions'][1]['label']);
        $this->assertSame('Expired reservations', $monitoring[$inventoryRequest->id]['exceptions'][0]['label']);
        $this->assertSame(1000.0, $monitoring[$financeRequest->id]['comparison'][0]['planned']);
        $this->assertSame(1000.0, $monitoring[$financeRequest->id]['comparison'][0]['current']);
        $this->assertSame(2.0, $monitoring[$purchaseRequest->id]['comparison'][0]['planned']);
        $this->assertSame(2.0, $monitoring[$purchaseRequest->id]['comparison'][0]['current']);
        $this->assertSame("/purchases?planning_request={$purchaseRequest->id}", $monitoring[$purchaseRequest->id]['direct_url']);
        $technician = User::factory()->create(['role' => 'technician']);
        $this->actingAs($technician)->post(route('planning-scenario-requests.exceptions.tasks.assignment.store', $operationsRequest), [
            'assignee_id' => $technician->id, 'due_on' => today()->addDays(3)->toDateString(),
            'governance_note' => 'Technician cannot govern the whole request.',
        ])->assertForbidden();
        $this->actingAs($admin)->post(route('planning-scenario-requests.exceptions.tasks.assignment.store', $operationsRequest), [
            'assignee_id' => $technician->id, 'due_on' => today()->addDays(3)->toDateString(),
            'governance_note' => 'Assign the outstanding planning work.',
        ])->assertRedirect();
        $this->assertSame($technician->id, $activity->fresh()->assigned_user_id);
        $this->assertSame(today()->addDays(3)->toDateString(), $activity->fresh()->due_at->toDateString());
        $this->assertNull($unrelated->fresh()->assigned_user_id);
        $this->assertDatabaseHas('security_events', ['type' => 'planning_scenario_tasks_assigned', 'user_id' => $admin->id]);

        $this->post(route('planning-scenario-requests.exceptions.reservations.store', $inventoryRequest), [
            'action' => 'renew', 'reserved_until' => today()->addDays(7)->toDateString(),
            'governance_note' => 'Keep materials assigned until the revised target.',
        ])->assertRedirect();
        $this->assertSame(today()->addDays(7)->toDateString(), $reservation->fresh()->reserved_until->toDateString());
        $this->assertDatabaseHas('security_events', ['type' => 'planning_scenario_reservations_renewed', 'user_id' => $admin->id]);
        $reservation->update(['reserved_until' => now()->subMinute()]);
        $this->post(route('planning-scenario-requests.exceptions.reservations.store', $inventoryRequest), [
            'action' => 'release', 'governance_note' => 'The material is no longer required.',
        ])->assertRedirect();
        $this->assertSame('released', $reservation->fresh()->status);
        $this->assertDatabaseHas('security_events', ['type' => 'planning_scenario_reservations_released', 'user_id' => $admin->id]);
        $this->post(route('planning-scenario-requests.exceptions.reservations.store', $inventoryRequest), [
            'action' => 'release', 'governance_note' => 'Idempotent retry.',
        ])->assertSessionHasErrors('governance');
        $this->get(route('planning-scenario-requests.index'))->assertInertia(fn (Assert $page) => $page->has('requests', 4));
    }

    /** @param array<string, mixed> $result */
    private function executedRequest(PlanningScenario $scenario, User $user, string $area, array $result): PlanningScenarioRequest
    {
        $payload = ['scenario' => ['name' => $scenario->name], 'horizon' => ['from' => '2026-09-01', 'to' => '2026-12-31']];
        $payload += match ($area) {
            'purchases' => ['shortages' => $result['drafts']],
            'finance' => ['cash_by_month' => $result['drafts']],
            default => [],
        };
        $request = PlanningScenarioRequest::create([
            'planning_scenario_id' => $scenario->id, 'area' => $area, 'status' => 'confirmed',
            'approved_fingerprint' => $scenario->approved_fingerprint, 'request_fingerprint' => str_repeat($area === 'purchases' ? 'b' : 'c', 64),
            'payload' => $payload,
            'generated_by' => $user->id, 'confirmed_by' => $user->id, 'confirmed_at' => now(),
            'materialization_status' => 'authorized', 'materialization_fingerprint' => str_repeat('d', 64),
            'idempotency_key' => match ($area) {
                'purchases' => '10000000-0000-4000-8000-000000000000',
                'finance' => '20000000-0000-4000-8000-000000000000',
                'operations' => '30000000-0000-4000-8000-000000000000',
                'inventory' => '40000000-0000-4000-8000-000000000000',
            },
            'execution_status' => 'executed', 'execution_result' => $result, 'executed_by' => $user->id, 'executed_at' => now(),
        ]);
        $request->update(['execution_fingerprint' => hash('sha256', json_encode([
            $request->id, $request->idempotency_key, $request->materialization_fingerprint, $result,
        ], JSON_THROW_ON_ERROR))]);

        return $request->refresh();
    }

    public function test_admin_freezes_a_non_committing_cross_area_portfolio_scenario(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Scenario', 'last_name' => 'Customer']);
        $withoutQuote = Opportunity::create([
            'person_id' => $customer->id, 'code' => 'OPP-SC-1', 'title' => 'Electrical expansion',
            'stage' => 'qualified', 'probability' => 50, 'estimated_value' => 10000, 'expected_close_on' => '2026-10-10',
        ]);
        $withQuote = Opportunity::create([
            'person_id' => $customer->id, 'code' => 'OPP-SC-2', 'title' => 'Security system',
            'stage' => 'proposal', 'probability' => 100, 'estimated_value' => 1800, 'expected_close_on' => '2026-11-10',
        ]);
        $product = Product::create([
            'type' => 'product', 'sku' => 'SEC-SCENARIO', 'name' => 'Security panel',
            'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 1000, 'cost_price' => 400, 'track_stock' => true, 'is_active' => true,
        ]);
        $quote = Quote::create([
            'opportunity_id' => $withQuote->id, 'person_id' => $customer->id, 'number' => 'PRE-SC-1',
            'version' => 1, 'status' => 'draft', 'approval_status' => 'not_required', 'issue_date' => '2026-08-29',
            'subtotal' => 2000, 'discount_amount' => 0, 'tax_amount' => 0, 'amount' => 2000,
        ]);
        $quote->lines()->create([
            'product_id' => $product->id, 'position' => 1, 'selection_mode' => 'included', 'is_selected' => true,
            'description' => 'Two security panels', 'quantity' => 2, 'unit_price' => 1000, 'discount_percent' => 0,
            'tax_percent' => 0, 'subtotal' => 2000, 'discount_amount' => 0, 'tax_amount' => 0, 'total' => 2000,
        ]);
        $warehouse = Warehouse::create(['code' => 'SCENARIO', 'name' => 'Scenario stock', 'is_default' => true, 'is_active' => true]);
        $movement = StockMovement::create(['created_by' => $admin->id, 'number' => 'MOV-SCENARIO', 'type' => 'receipt', 'posted_at' => now()]);
        $movement->lines()->create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 400]);

        $this->actingAs($admin)->post(route('planning-scenarios.store'), [
            'name' => 'Base Q4',
            'scenario_kind' => 'base',
            'horizon_starts_on' => '2026-09-01',
            'horizon_ends_on' => '2026-12-31',
            'opportunity_ids' => [$withoutQuote->id, $withQuote->id],
            'capacity_hours_per_opportunity' => 40,
            'material_cost_percent_without_quote' => 35,
            'collection_delay_days' => 30,
        ])->assertRedirect();

        $scenario = PlanningScenario::firstOrFail();
        $this->assertSame('frozen', $scenario->status);
        $this->assertSame('base', $scenario->scenario_kind);
        $this->assertSame(2, $scenario->result_snapshot['summary']['opportunities']);
        $this->assertSame(12000.0, (float) $scenario->result_snapshot['summary']['confirmed_value']);
        $this->assertSame(7000.0, (float) $scenario->result_snapshot['summary']['weighted_revenue']);
        $this->assertSame(60.0, (float) $scenario->result_snapshot['summary']['capacity_hours']);
        $this->assertSame(2550.0, (float) $scenario->result_snapshot['summary']['expected_material_cost']);
        $this->assertSame(2.0, (float) $scenario->result_snapshot['materials'][0]['expected_quantity']);
        $this->assertTrue($scenario->result_snapshot['non_committing']);
        $this->assertSame(60.0, (float) $scenario->reference_snapshot['capacity']['scenario_overload_hours']);
        $this->assertSame(1, $scenario->reference_snapshot['stock']['products_at_risk']);
        $this->assertSame(1.0, (float) $scenario->reference_snapshot['stock']['shortage_units']);
        $this->assertSame(1.0, (float) $scenario->reference_snapshot['stock']['products'][0]['free_quantity']);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseCount('purchase_documents', 0);
        $this->assertDatabaseCount('work_orders', 0);
        $this->assertDatabaseCount('financial_forecast_items', 0);
        $this->assertDatabaseHas('security_events', ['type' => 'planning_scenario_frozen', 'user_id' => $admin->id]);

        $this->get(route('planning-scenarios.index', ['scenario' => $scenario]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('planning-scenarios/index')
                ->where('selectedScenario.id', $scenario->id)
                ->where('selectedScenario.fingerprint', $scenario->fingerprint));

        $this->post(route('planning-scenarios.store'), [
            'name' => 'Risk Q4',
            'scenario_kind' => 'risk',
            'baseline_scenario_id' => $scenario->id,
            'horizon_starts_on' => '2026-09-01',
            'horizon_ends_on' => '2026-12-31',
            'opportunity_ids' => [$withoutQuote->id, $withQuote->id],
            'capacity_hours_per_opportunity' => 80,
            'material_cost_percent_without_quote' => 50,
            'collection_delay_days' => 45,
        ])->assertRedirect();

        $risk = PlanningScenario::where('scenario_kind', 'risk')->firstOrFail();
        $this->assertSame($scenario->id, $risk->baseline_scenario_id);
        $this->assertSame(60.0, (float) $risk->comparison_snapshot['deltas']['capacity_hours']);
        $this->assertSame(60.0, (float) $risk->comparison_snapshot['capacity']['overload_hours_delta']);
        $this->assertSame(0.0, (float) $risk->comparison_snapshot['stock']['shortage_units_delta']);
        $this->assertSame([], $risk->comparison_snapshot['portfolio']['added_opportunity_ids']);

        $this->post(route('planning-scenarios.approval.store', $risk), [
            'fingerprint' => $risk->fingerprint,
            'approval_note' => 'Capacity, stock and risk variation reviewed.',
        ])->assertRedirect();
        $risk->refresh();
        $this->assertSame('approved', $risk->status);
        $this->assertSame($admin->id, $risk->approved_by);
        $this->assertSame($risk->fingerprint, $risk->approved_fingerprint);
        $this->assertSame('Capacity, stock and risk variation reviewed.', $risk->approval_note);
        $this->assertDatabaseHas('security_events', [
            'type' => 'planning_scenario_approved',
            'user_id' => $admin->id,
        ]);
        $this->post(route('planning-scenarios.approval.store', $risk), [
            'fingerprint' => $risk->fingerprint,
        ])->assertSessionHasErrors('approval');

        $this->post(route('planning-scenarios.requests.store', $risk), [
            'fingerprint' => $risk->approved_fingerprint,
        ])->assertRedirect(route('planning-scenario-requests.index'));
        $this->assertDatabaseCount('planning_scenario_requests', 4);
        $this->assertSame(
            ['finance', 'inventory', 'operations', 'purchases'],
            PlanningScenarioRequest::query()->orderBy('area')->pluck('area')->all(),
        );
        $operationRequest = PlanningScenarioRequest::where('area', 'operations')->firstOrFail();
        $this->assertSame('draft', $operationRequest->status);
        $this->assertSame($risk->approved_fingerprint, $operationRequest->approved_fingerprint);
        $this->assertTrue($operationRequest->payload['non_committing']);
        $this->assertDatabaseHas('security_events', ['type' => 'planning_scenario_requests_generated']);
        $this->post(route('planning-scenarios.requests.store', $risk), [
            'fingerprint' => $risk->approved_fingerprint,
        ])->assertSessionHasErrors('requests');

        $purchaseRequest = PlanningScenarioRequest::where('area', 'purchases')->firstOrFail();
        $purchaseRequest->update(['payload' => [...$purchaseRequest->payload, 'expected_material_cost' => 999999]]);
        $this->post(route('planning-scenario-requests.confirmation.store', $purchaseRequest), [
            'fingerprint' => $purchaseRequest->request_fingerprint,
            'confirmation_note' => 'Tampered request.',
        ])->assertSessionHasErrors('confirmation');

        $technician = User::factory()->create(['role' => 'technician']);
        $inventoryRequest = PlanningScenarioRequest::where('area', 'inventory')->firstOrFail();
        $this->actingAs($technician)->post(route('planning-scenario-requests.confirmation.store', $inventoryRequest), [
            'fingerprint' => $inventoryRequest->request_fingerprint,
            'confirmation_note' => 'Inventory reviewed.',
        ])->assertForbidden();
        $this->get(route('planning-scenario-requests.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('planning-scenario-requests/index')
                ->has('requests', 1)
                ->where('requests.0.area', 'operations'));
        $this->post(route('planning-scenario-requests.confirmation.store', $operationRequest), [
            'fingerprint' => str_repeat('b', 64),
            'confirmation_note' => 'Operations reviewed.',
        ])->assertSessionHasErrors('confirmation');
        $this->post(route('planning-scenario-requests.confirmation.store', $operationRequest), [
            'fingerprint' => $operationRequest->request_fingerprint,
            'confirmation_note' => 'Capacity and opportunity workload reviewed.',
        ])->assertRedirect();
        $operationRequest->refresh();
        $this->assertSame('confirmed', $operationRequest->status);
        $this->assertSame($technician->id, $operationRequest->confirmed_by);
        $this->assertDatabaseHas('security_events', [
            'type' => 'planning_scenario_request_confirmed',
            'user_id' => $technician->id,
        ]);
        $this->post(route('planning-scenario-requests.confirmation.store', $operationRequest), [
            'fingerprint' => $operationRequest->request_fingerprint,
            'confirmation_note' => 'Duplicate confirmation.',
        ])->assertSessionHasErrors('confirmation');

        $this->post(route('planning-scenario-requests.materialization.store', $operationRequest), [
            'fingerprint' => $operationRequest->request_fingerprint,
        ])->assertRedirect();
        $operationRequest->refresh();
        $this->assertSame('prepared', $operationRequest->materialization_status);
        $this->assertNotNull($operationRequest->idempotency_key);
        $this->assertSame(0, $operationRequest->materialization_preview['state']['summary']['blocking_conflicts']);
        $this->assertDatabaseHas('security_events', [
            'type' => 'planning_scenario_materialization_prepared',
            'user_id' => $technician->id,
        ]);
        $this->post(route('planning-scenario-requests.materialization.authorization.store', $operationRequest), [
            'fingerprint' => $operationRequest->materialization_fingerprint,
            'authorization_note' => 'Same-user authorization attempt.',
        ])->assertSessionHasErrors('authorization');

        $this->actingAs($admin)->post(route('planning-scenario-requests.materialization.authorization.store', $operationRequest), [
            'fingerprint' => str_repeat('c', 64),
            'authorization_note' => 'Stale preview attempt.',
        ])->assertSessionHasErrors('authorization');
        $this->post(route('planning-scenario-requests.materialization.authorization.store', $operationRequest), [
            'fingerprint' => $operationRequest->materialization_fingerprint,
            'authorization_note' => 'Live capacity preview independently reviewed.',
        ])->assertRedirect();
        $operationRequest->refresh();
        $this->assertSame('authorized', $operationRequest->materialization_status);
        $this->assertSame($admin->id, $operationRequest->authorized_by);
        $this->assertDatabaseHas('security_events', [
            'type' => 'planning_scenario_materialization_authorized',
            'user_id' => $admin->id,
        ]);
        $this->post(route('planning-scenario-requests.execution.store', $operationRequest), [
            'fingerprint' => $operationRequest->materialization_fingerprint,
            'idempotency_key' => '00000000-0000-4000-8000-000000000000',
        ])->assertSessionHasErrors('execution');
        $this->post(route('planning-scenario-requests.execution.store', $operationRequest), [
            'fingerprint' => $operationRequest->materialization_fingerprint,
            'idempotency_key' => $operationRequest->idempotency_key,
        ])->assertRedirect();
        $operationRequest->refresh();
        $this->assertSame('executed', $operationRequest->execution_status);
        $this->assertSame('operational_agenda', $operationRequest->execution_result['kind']);
        $this->assertCount(2, $operationRequest->execution_result['activity_ids']);
        $this->assertDatabaseCount('crm_activities', 2);
        $this->assertDatabaseHas('security_events', [
            'type' => 'planning_scenario_request_executed',
            'user_id' => $admin->id,
        ]);
        $this->post(route('planning-scenario-requests.execution.store', $operationRequest), [
            'fingerprint' => $operationRequest->materialization_fingerprint,
            'idempotency_key' => $operationRequest->idempotency_key,
        ])->assertSessionHasErrors('execution');
        $this->assertDatabaseCount('crm_activities', 2);
        $this->post(route('planning-scenario-requests.materialization.store', $operationRequest), [
            'fingerprint' => $operationRequest->request_fingerprint,
        ])->assertSessionHasErrors('materialization');

        $this->post(route('planning-scenario-requests.confirmation.store', $inventoryRequest), [
            'fingerprint' => $inventoryRequest->request_fingerprint,
            'confirmation_note' => 'Inventory shortage reviewed.',
        ])->assertRedirect();
        $inventoryRequest->refresh();
        $this->post(route('planning-scenario-requests.materialization.store', $inventoryRequest), [
            'fingerprint' => $inventoryRequest->request_fingerprint,
        ])->assertRedirect();
        $inventoryRequest->refresh();
        $this->assertSame(2, $inventoryRequest->materialization_preview['state']['summary']['blocking_conflicts']);
        $this->post(route('planning-scenario-requests.materialization.authorization.store', $inventoryRequest), [
            'fingerprint' => $inventoryRequest->materialization_fingerprint,
            'authorization_note' => 'Cannot authorize with shortage.',
        ])->assertSessionHasErrors('authorization');

        $withoutQuote->update(['estimated_value' => 99999, 'probability' => 1]);
        $this->assertSame(10000.0, (float) $scenario->fresh()->opportunity_snapshot['opportunities'][0]['confirmed']['value']);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseCount('purchase_documents', 0);
        $this->assertDatabaseCount('work_orders', 0);
        $this->assertDatabaseCount('financial_forecast_items', 0);
    }

    public function test_scenarios_are_admin_only_and_reject_closed_opportunities(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Closed']);
        $closed = Opportunity::create([
            'person_id' => $customer->id, 'code' => 'OPP-CLOSED', 'title' => 'Closed sale',
            'stage' => 'won', 'probability' => 100, 'estimated_value' => 5000,
        ]);

        $this->actingAs($commercial)->get(route('planning-scenarios.index'))->assertForbidden();
        $this->post(route('planning-scenarios.store'), [])->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('planning-scenarios.store'), [
            'name' => 'Invalid closed portfolio',
            'scenario_kind' => 'base',
            'horizon_starts_on' => '2026-09-01',
            'horizon_ends_on' => '2026-12-31',
            'opportunity_ids' => [$closed->id],
            'capacity_hours_per_opportunity' => 40,
            'material_cost_percent_without_quote' => 35,
            'collection_delay_days' => 30,
        ])->assertSessionHasErrors('opportunity_ids');

        $open = Opportunity::create([
            'person_id' => $customer->id, 'code' => 'OPP-OPEN', 'title' => 'Open sale',
            'stage' => 'qualified', 'probability' => 50, 'estimated_value' => 3000,
        ]);
        $this->post(route('planning-scenarios.store'), [
            'name' => 'Risk without baseline',
            'scenario_kind' => 'risk',
            'horizon_starts_on' => '2026-09-01',
            'horizon_ends_on' => '2026-12-31',
            'opportunity_ids' => [$open->id],
            'capacity_hours_per_opportunity' => 40,
            'material_cost_percent_without_quote' => 35,
            'collection_delay_days' => 30,
        ])->assertSessionHasErrors('baseline_scenario_id');

        $this->assertDatabaseCount('planning_scenarios', 0);
    }

    public function test_approval_rejects_stale_expired_or_corrupted_scenarios_and_remains_admin_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $commercial = User::factory()->create(['role' => 'commercial']);
        $scenario = PlanningScenario::create([
            'name' => 'Governed scenario',
            'version' => 1,
            'status' => 'frozen',
            'scenario_kind' => 'base',
            'horizon_starts_on' => '2026-09-01',
            'horizon_ends_on' => '2026-12-31',
            'assumptions' => ['capacity_hours_per_opportunity' => 40],
            'opportunity_snapshot' => ['opportunities' => []],
            'result_snapshot' => ['summary' => ['opportunities' => 0], 'materials' => [], 'cash_by_month' => [], 'non_committing' => true],
            'reference_snapshot' => ['captured_at' => now()->toIso8601String(), 'capacity' => [], 'stock' => []],
            'reference_valid_until' => now()->addDay(),
            'comparison_snapshot' => null,
            'fingerprint' => str_repeat('0', 64),
            'created_by' => $admin->id,
        ]);
        $scenario->update(['fingerprint' => app(PlanningScenarioIntegrity::class)->for($scenario)]);
        $fingerprint = $scenario->fresh()->fingerprint;

        $this->actingAs($commercial)->post(route('planning-scenarios.approval.store', $scenario), [
            'fingerprint' => $fingerprint,
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('planning-scenarios.approval.store', $scenario), [
            'fingerprint' => str_repeat('a', 64),
        ])->assertSessionHasErrors('approval');

        $scenario->update(['reference_valid_until' => now()->subMinute()]);
        $this->post(route('planning-scenarios.approval.store', $scenario), [
            'fingerprint' => $fingerprint,
        ])->assertSessionHasErrors('approval');

        $scenario->update([
            'reference_valid_until' => now()->addDay(),
            'assumptions' => ['capacity_hours_per_opportunity' => 999],
        ]);
        $this->post(route('planning-scenarios.approval.store', $scenario), [
            'fingerprint' => $fingerprint,
        ])->assertSessionHasErrors('approval');

        $this->assertSame('frozen', $scenario->fresh()->status);
        $this->assertDatabaseMissing('security_events', ['type' => 'planning_scenario_approved']);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseCount('purchase_documents', 0);
        $this->assertDatabaseCount('work_orders', 0);
        $this->assertDatabaseCount('financial_forecast_items', 0);
    }

    public function test_admin_records_immutable_reviews_and_closes_an_executed_scenario_without_altering_resources(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $commercial = User::factory()->create(['role' => 'commercial']);
        $scenario = PlanningScenario::create([
            'name' => 'Reviewed horizon', 'version' => 1, 'status' => 'approved', 'scenario_kind' => 'base',
            'horizon_starts_on' => '2026-09-01', 'horizon_ends_on' => '2026-12-31',
            'assumptions' => ['capacity_hours_per_opportunity' => 40],
            'opportunity_snapshot' => ['opportunities' => []],
            'result_snapshot' => ['summary' => ['opportunities' => 0], 'materials' => [], 'cash_by_month' => [], 'non_committing' => true],
            'reference_snapshot' => ['captured_at' => now()->toIso8601String(), 'capacity' => [], 'stock' => []],
            'comparison_snapshot' => null, 'fingerprint' => str_repeat('0', 64),
            'created_by' => $admin->id, 'approved_by' => $admin->id, 'approved_at' => now(),
        ]);
        $fingerprint = app(PlanningScenarioIntegrity::class)->for($scenario);
        $scenario->update(['fingerprint' => $fingerprint, 'approved_fingerprint' => $fingerprint]);
        $operationsRequest = $this->executedRequest($scenario->fresh(), $admin, 'operations', [
            'kind' => 'operational_agenda', 'activity_ids' => [],
        ]);
        $activity = CrmActivity::create([
            'planning_scenario_request_id' => $operationsRequest->id, 'created_by' => $admin->id,
            'type' => 'task', 'title' => 'Pending planning result', 'status' => 'pending', 'due_at' => now()->subDay(),
        ]);

        $this->actingAs($commercial)->post(route('planning-scenarios.reviews.store', $scenario), [
            'approved_fingerprint' => $fingerprint,
            'explanation' => 'Commercial users cannot govern scenario reviews.',
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('planning-scenarios.reviews.store', $scenario), [
            'approved_fingerprint' => $fingerprint,
            'explanation' => 'The open task remains overdue and still needs an owner.',
        ])->assertRedirect();

        $review = $scenario->reviews()->firstOrFail();
        $this->assertSame(1, $review->sequence);
        $this->assertSame(1, $review->outcome_snapshot['summary']['resources']);
        $this->assertSame(2, $review->outcome_snapshot['summary']['pending_exceptions']);
        $this->assertDatabaseHas('security_events', ['type' => 'planning_scenario_review_recorded', 'user_id' => $admin->id]);

        $this->post(route('planning-scenarios.closure.store', $scenario), [
            'approved_fingerprint' => $fingerprint,
            'review_fingerprint' => str_repeat('f', 64),
            'closure_note' => 'Close with a stale review fingerprint.',
            'acknowledge_exceptions' => '1',
        ])->assertSessionHasErrors('closure');
        $this->post(route('planning-scenarios.closure.store', $scenario), [
            'approved_fingerprint' => $fingerprint,
            'review_fingerprint' => $review->fingerprint,
            'closure_note' => 'Close without acknowledging the remaining exceptions.',
        ])->assertSessionHasErrors('acknowledge_exceptions');

        $activity->update(['assigned_user_id' => $admin->id, 'due_at' => now()->addDay()]);
        $this->post(route('planning-scenarios.closure.store', $scenario), [
            'approved_fingerprint' => $fingerprint,
            'review_fingerprint' => $review->fingerprint,
            'closure_note' => 'The result changed after the review and must be reviewed again.',
            'acknowledge_exceptions' => '1',
        ])->assertSessionHasErrors('closure');
        $this->post(route('planning-scenarios.reviews.store', $scenario), [
            'approved_fingerprint' => $fingerprint,
            'explanation' => 'The overdue task now has an owner and a future target.',
        ])->assertRedirect();
        $latestReview = $scenario->reviews()->reorder()->latest('sequence')->firstOrFail();
        $this->assertSame(2, $latestReview->sequence);
        $this->assertSame(0, $latestReview->outcome_snapshot['summary']['pending_exceptions']);

        $this->post(route('planning-scenarios.closure.store', $scenario), [
            'approved_fingerprint' => $fingerprint,
            'review_fingerprint' => $latestReview->fingerprint,
            'closure_note' => 'The horizon is complete and native follow-up will continue.',
            'acknowledge_exceptions' => '1',
        ])->assertRedirect();

        $closed = $scenario->fresh();
        $this->assertSame('closed', $closed->status);
        $this->assertSame($admin->id, $closed->closed_by);
        $this->assertNotNull($closed->closed_at);
        $this->assertNotNull($closed->closure_fingerprint);
        $this->assertSame(0, $closed->closure_snapshot['summary']['pending_exceptions']);
        $this->assertSame(2, $review->fresh()->outcome_snapshot['summary']['pending_exceptions']);
        $this->assertDatabaseHas('crm_activities', ['id' => $activity->id, 'status' => 'pending']);
        $this->assertDatabaseHas('security_events', ['type' => 'planning_scenario_closed', 'user_id' => $admin->id]);

        $this->post(route('planning-scenarios.reviews.store', $scenario), [
            'approved_fingerprint' => $fingerprint,
            'explanation' => 'Closed scenarios cannot receive another review.',
        ])->assertSessionHasErrors('lifecycle');
        $this->get(route('planning-scenarios.index', ['scenario' => $scenario]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedScenario.status', 'closed')
                ->where('selectedScenario.reviews.0.id', $review->id)
                ->where('selectedScenario.reviews.1.id', $latestReview->id)
                ->where('selectedScenario.closer.id', $admin->id)
                ->where('selectedScenario.closure_snapshot.summary.pending_exceptions', 0)
                ->where('selectedScenario.learning_summary.source', 'immutable_closure')
                ->where('selectedScenario.learning_summary.auto_apply', false)
                ->where('selectedScenario.learning_summary.summary.metrics', 2)
                ->where('selectedScenario.learning_summary.summary.aligned', 1)
                ->where('selectedScenario.learning_summary.summary.significant', 1)
                ->where('selectedScenario.learning_summary.recommendations.0.area', 'operations'));
    }
}
