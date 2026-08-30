<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Person;
use App\Models\PlanningScenario;
use App\Models\PlanningScenarioRequest;
use App\Models\Product;
use App\Models\Quote;
use App\Models\StockMovement;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Planning\PlanningScenarioIntegrity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlanningScenarioTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertSame(1, $inventoryRequest->materialization_preview['state']['summary']['blocking_conflicts']);
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
}
