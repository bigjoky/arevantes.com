<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Person;
use App\Models\PlanningScenario;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
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

        $this->actingAs($admin)->post(route('planning-scenarios.store'), [
            'name' => 'Base Q4',
            'horizon_starts_on' => '2026-09-01',
            'horizon_ends_on' => '2026-12-31',
            'opportunity_ids' => [$withoutQuote->id, $withQuote->id],
            'capacity_hours_per_opportunity' => 40,
            'material_cost_percent_without_quote' => 35,
            'collection_delay_days' => 30,
        ])->assertRedirect();

        $scenario = PlanningScenario::firstOrFail();
        $this->assertSame('frozen', $scenario->status);
        $this->assertSame(2, $scenario->result_snapshot['summary']['opportunities']);
        $this->assertSame(12000.0, (float) $scenario->result_snapshot['summary']['confirmed_value']);
        $this->assertSame(7000.0, (float) $scenario->result_snapshot['summary']['weighted_revenue']);
        $this->assertSame(60.0, (float) $scenario->result_snapshot['summary']['capacity_hours']);
        $this->assertSame(2550.0, (float) $scenario->result_snapshot['summary']['expected_material_cost']);
        $this->assertSame(2.0, (float) $scenario->result_snapshot['materials'][0]['expected_quantity']);
        $this->assertTrue($scenario->result_snapshot['non_committing']);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseCount('purchase_documents', 0);
        $this->assertDatabaseCount('work_orders', 0);
        $this->assertDatabaseCount('financial_forecast_items', 0);
        $this->assertDatabaseHas('security_events', ['type' => 'planning_scenario_frozen', 'user_id' => $admin->id]);

        $withoutQuote->update(['estimated_value' => 99999, 'probability' => 1]);
        $this->assertSame(10000.0, (float) PlanningScenario::firstOrFail()->opportunity_snapshot['opportunities'][0]['confirmed']['value']);

        $this->get(route('planning-scenarios.index', ['scenario' => $scenario]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('planning-scenarios/index')
                ->where('selectedScenario.id', $scenario->id)
                ->where('selectedScenario.fingerprint', $scenario->fingerprint));
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
            'horizon_starts_on' => '2026-09-01',
            'horizon_ends_on' => '2026-12-31',
            'opportunity_ids' => [$closed->id],
            'capacity_hours_per_opportunity' => 40,
            'material_cost_percent_without_quote' => 35,
            'collection_delay_days' => 30,
        ])->assertSessionHasErrors('opportunity_ids');

        $this->assertDatabaseCount('planning_scenarios', 0);
    }
}
