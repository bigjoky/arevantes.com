<?php

namespace Tests\Feature;

use App\Models\FinancialForecastItem;
use App\Models\Installation;
use App\Models\InstallationAsset;
use App\Models\MaintenancePlan;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\ServiceSlaPolicy;
use App\Models\ServiceTicket;
use App\Models\StockReservation;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\AfterSales\MaintenancePlanLifecycle;
use App\Services\AfterSales\SlaManager;
use App\Services\Supply\PreventiveDemandForecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AfterSalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // These contract assertions deliberately exercise the optional English UI.
        $this->withSession(['locale' => 'en']);
        app()->setLocale('en');
    }

    public function test_each_service_workspace_loads_only_its_contextual_dataset(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($commercial);

        $this->get(route('after-sales.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('after-sales/index')
            ->where('initialSection', 'tickets')
            ->where('plans', [])
            ->where('warranties', [])
            ->where('serviceCapacity', null)
            ->where('serviceEconomics', null));

        $this->get(route('maintenance.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('maintenance/index')
            ->where('initialSection', 'maintenance')
            ->where('tickets', [])
            ->where('warranties', [])
            ->has('serviceCapacity')
            ->where('serviceEconomics', null));

        $this->get(route('contracts-services.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('contracts-services/index')
            ->where('initialSection', 'contracts')
            ->where('tickets', [])
            ->where('warranties', [])
            ->where('serviceCapacity', null)
            ->has('serviceEconomics'));

        $this->get(route('assets.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('assets/index')
            ->where('initialSection', 'assets')
            ->where('tickets', [])
            ->where('plans', [])
            ->where('serviceCapacity', null)
            ->where('serviceEconomics', null));
    }

    public function test_a_commercial_can_define_an_sla_policy(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);

        $this->actingAs($commercial)->post(route('after-sales.sla-policies.store'), [
            'name' => 'Urgent customer outage',
            'priority' => 'urgent',
            'first_response_minutes' => 15,
            'resolution_minutes' => 240,
            'escalation_minutes' => 30,
        ])->assertRedirect();

        $this->assertDatabaseHas('service_sla_policies', [
            'name' => 'Urgent customer outage',
            'first_response_minutes' => 15,
        ]);
    }

    public function test_a_service_ticket_creates_a_work_order_with_the_correct_checklist(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create(['service_type' => 'ev_charger', 'status' => 'completed', 'site_address_line_1' => 'Calle Carga 1']);
        $this->actingAs($commercial)->post(route('after-sales.tickets.store'), ['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'kind' => 'incident', 'priority' => 'high', 'title' => 'Charger does not start', 'description' => 'Check protections and commissioning'])->assertRedirect();
        $ticket = ServiceTicket::firstOrFail();
        $this->assertSame('SAT-2026-00001', $ticket->number);

        $order = WorkOrder::firstOrFail();
        $this->assertSame($order->id, $ticket->refresh()->work_order_id);
        $this->assertSame($technician->id, $order->assigned_user_id);
        $this->assertGreaterThanOrEqual(8, $order->checklistItems()->count());
        $this->assertTrue($order->checklistItems()->where('label', 'Complete charging test')->exists());
        $this->post(route('after-sales.tickets.work-order', $ticket))->assertUnprocessable();
        $this->assertNotNull($ticket->refresh()->first_response_due_at);
        $this->assertNotNull($ticket->resolution_due_at);
    }

    public function test_resolving_maintenance_advances_the_plan_and_warranty_dates_are_tracked(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'completed', 'site_address_line_1' => 'Calle Solar 2']);
        $plan = MaintenancePlan::create(['installation_id' => $installation->id, 'name' => 'Annual PV inspection', 'interval_months' => 12, 'starts_on' => '2026-01-01', 'next_due_on' => '2026-07-01', 'is_active' => true]);
        $ticket = ServiceTicket::create(['installation_id' => $installation->id, 'maintenance_plan_id' => $plan->id, 'created_by' => $commercial->id, 'number' => 'SAT-2026-00002', 'kind' => 'maintenance', 'priority' => 'normal', 'status' => 'open', 'title' => 'Annual PV inspection']);
        $this->actingAs($commercial)->patch(route('after-sales.tickets.update', $ticket), ['status' => 'resolved', 'resolution' => 'Inspection passed'])->assertRedirect();
        $this->assertSame('2027-07-01', $plan->refresh()->next_due_on->toDateString());
        $resolvedAt = $ticket->refresh()->resolved_at;
        $this->travel(1)->days();
        $this->patch(route('after-sales.tickets.update', $ticket), ['status' => 'resolved', 'resolution' => 'Inspection passed'])->assertRedirect();
        $this->assertSame('2027-07-01', $plan->refresh()->next_due_on->toDateString());
        $this->assertTrue($resolvedAt->equalTo($ticket->refresh()->resolved_at));

        $product = Product::create(['type' => 'product', 'name' => 'Inverter', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 1000, 'cost_price' => 700, 'track_stock' => true, 'track_serials' => true, 'is_active' => true]);
        $serial = SerialNumber::create(['product_id' => $product->id, 'installation_id' => $installation->id, 'serial_number' => 'INV-WARRANTY-1', 'status' => 'installed']);
        $this->patch(route('after-sales.warranties.update', $serial), ['warranty_starts_on' => '2026-07-01', 'warranty_ends_on' => '2036-07-01'])->assertRedirect();
        $this->assertSame('2036-07-01', $serial->refresh()->warranty_ends_on->toDateString());
    }

    public function test_a_maintenance_plan_can_be_fully_edited_and_still_paused_independently(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'completed', 'site_address_line_1' => 'Calle Plan 1']);
        $plan = MaintenancePlan::create([
            'installation_id' => $installation->id,
            'name' => 'Basic maintenance',
            'interval_months' => 12,
            'starts_on' => '2026-01-01',
            'next_due_on' => '2027-01-01',
            'is_active' => true,
        ]);

        $this->actingAs($commercial)->patch(route('after-sales.plans.update', $plan), [
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'work_order_template_id' => 'none',
            'name' => 'Complete inverter maintenance',
            'interval_months' => 6,
            'lead_days' => 21,
            'starts_on' => '2026-02-01',
            'next_due_on' => '2026-08-01',
            'generate_work_order' => true,
            'scope' => 'Inspect protections, firmware and ventilation.',
            'change_note' => 'Expanded preventive scope and increased visit frequency.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame('Complete inverter maintenance', $plan->name);
        $this->assertSame($technician->id, $plan->assigned_user_id);
        $this->assertSame(6, $plan->interval_months);
        $this->assertSame(21, $plan->lead_days);
        $this->assertSame('2026-08-01', $plan->next_due_on->toDateString());
        $this->assertTrue($plan->generate_work_order);
        $this->assertSame('Inspect protections, firmware and ventilation.', $plan->scope);

        $this->patch(route('after-sales.plans.update', $plan), ['is_active' => false, 'change_note' => 'Plan paused at customer request.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($plan->refresh()->is_active);
    }

    public function test_service_plan_is_versioned_forecast_and_prepares_one_human_reviewed_renewal(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Industria', 'company_name' => 'Industria Norte SL']);
        $installation = Installation::create([
            'person_id' => $customer->id,
            'commercial_user_id' => $commercial->id,
            'service_type' => 'security',
            'status' => 'completed',
            'project_reference' => 'SEG-REC-1',
            'site_address_line_1' => 'Calle Seguridad 1',
        ]);
        $panel = InstallationAsset::create(['installation_id' => $installation->id, 'asset_tag' => 'SEG-001', 'name' => 'Alarm panel', 'category' => 'alarm_panel', 'status' => 'active']);
        $camera = InstallationAsset::create(['installation_id' => $installation->id, 'asset_tag' => 'SEG-002', 'name' => 'Perimeter camera', 'category' => 'camera', 'status' => 'active']);

        $this->actingAs($commercial)->post(route('after-sales.plans.store'), [
            'installation_id' => $installation->id,
            'name' => 'Security response and preventive care',
            'service_level' => 'priority',
            'coverage_type' => 'parts_and_labour',
            'covered_asset_ids' => [$panel->id, $camera->id],
            'included_visits' => 4,
            'response_minutes' => 60,
            'resolution_minutes' => 480,
            'interval_months' => 3,
            'lead_days' => 14,
            'starts_on' => '2026-09-01',
            'contract_ends_on' => '2027-08-31',
            'renewal_notice_days' => 60,
            'billing_frequency_months' => 3,
            'billing_amount' => 300,
            'next_billing_on' => '2026-09-01',
            'next_due_on' => '2026-12-01',
            'generate_work_order' => true,
            'scope' => 'Panel, detectors, cameras and communications.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $plan = MaintenancePlan::firstOrFail();
        $this->assertSame(1, $plan->version);
        $this->assertEqualsCanonicalizing([$panel->id, $camera->id], $plan->coveredAssets()->pluck('installation_assets.id')->all());
        $this->assertSame('Initial service plan created.', $plan->versions()->firstOrFail()->change_note);
        $forecast = FinancialForecastItem::where('maintenance_plan_id', $plan->id)->firstOrFail();
        $this->assertSame('quarterly', $forecast->recurrence);
        $this->assertSame('300.00', $forecast->amount);
        $this->assertSame('planned', $forecast->status);

        $this->patch(route('after-sales.plans.update', $plan), [
            'coverage_type' => 'full',
            'covered_asset_ids' => [$panel->id],
            'billing_amount' => 350,
            'billing_frequency_months' => 3,
            'next_billing_on' => '2026-12-01',
            'change_note' => 'Camera excluded and full panel coverage approved.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame(2, $plan->version);
        $this->assertSame('full', $plan->coverage_type);
        $this->assertSame([$panel->id], $plan->coveredAssets()->pluck('installation_assets.id')->all());
        $this->assertSame('parts_and_labour', $plan->versions()->where('version', 1)->firstOrFail()->snapshot['coverage_type']);
        $this->assertSame('300.00', $forecast->refresh()->amount);
        $this->assertSame('cancelled', $forecast->status);
        $this->assertSame(2, FinancialForecastItem::where('maintenance_plan_id', $plan->id)->count());
        $this->assertSame('350.00', FinancialForecastItem::where('maintenance_plan_id', $plan->id)->where('maintenance_plan_version', 2)->sole()->amount);

        $this->post(route('after-sales.plans.renewal', $plan))->assertRedirect();
        $renewal = Opportunity::where('commercial_kind', 'renewal')->firstOrFail();
        $this->assertSame($customer->id, $renewal->person_id);
        $this->assertSame($installation->id, $renewal->installation_id);
        $this->assertSame('new', $renewal->stage);
        $this->assertSame('1400.00', $renewal->estimated_value);
        $this->assertNotNull($renewal->nextActivity);
        $this->assertSame($renewal->id, $plan->refresh()->renewal_opportunity_id);

        $this->post(route('after-sales.plans.renewal', $plan))->assertRedirect();
        $this->assertDatabaseCount('opportunities', 1);
    }

    public function test_preventive_materials_are_versioned_forecasts_without_stock_commitments(): void
    {
        $this->travelTo('2026-08-26 09:00:00');
        $commercial = User::factory()->create(['role' => 'commercial']);
        $installation = Installation::create(['service_type' => 'electrical', 'status' => 'completed', 'project_reference' => 'ELEC-SVC-1', 'site_address_line_1' => 'Calle Cuadro 1']);
        $plan = app(MaintenancePlanLifecycle::class)->create([
            'installation_id' => $installation->id,
            'name' => 'Quarterly electrical care',
            'interval_months' => 3,
            'starts_on' => today(),
            'next_due_on' => today()->addMonth(),
        ], [], $commercial);
        $product = Product::create([
            'type' => 'product',
            'sku' => 'FUSE-SVC',
            'name' => 'Service fuse',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 10,
            'cost_price' => 4,
            'track_stock' => true,
            'track_serials' => false,
            'supply_criticality' => 'critical',
            'preferred_lead_time_days' => 14,
            'is_active' => true,
        ]);

        $payload = [
            'product_id' => $product->id,
            'quantity_per_visit' => 2,
            'expected_usage_percent' => 50,
            'minimum_stock' => 3,
            'notes' => 'Carry only when inspection confirms wear.',
            'change_note' => 'Added expected fuse demand for preventive visits.',
        ];
        $this->actingAs($commercial)->post(route('after-sales.plans.materials.store', $plan), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame(2, $plan->version);
        $this->assertDatabaseHas('maintenance_plan_materials', ['maintenance_plan_id' => $plan->id, 'product_id' => $product->id, 'quantity_per_visit' => 2]);
        $this->assertSame(2, $plan->versions()->count());
        $this->assertSame($product->id, $plan->versions()->where('version', 2)->firstOrFail()->snapshot['preventive_materials'][0]['product_id']);

        $forecast = app(PreventiveDemandForecast::class)->forecast(today(), 180);
        $this->assertSame(1, $forecast['summary']['products']);
        $this->assertSame(2, $forecast['summary']['visits']);
        $this->assertSame(2.0, $forecast['rows'][0]['expected_quantity']);
        $this->assertSame(5.0, $forecast['rows'][0]['forecast_shortage']);
        $this->assertSame(20.0, $forecast['rows'][0]['forecast_exposure']);
        $this->assertSame(0, StockReservation::count());

        $material = $plan->preventiveMaterials()->firstOrFail();
        $this->delete(route('after-sales.plans.materials.destroy', [$plan, $material]), ['change_note' => 'Fuse removed after service procedure review.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3, $plan->refresh()->version);
        $this->assertDatabaseCount('maintenance_plan_materials', 0);
        $this->assertSame(0, StockReservation::count());
    }

    public function test_sla_uses_the_customer_specific_policy_and_respects_the_escalation_interval(): void
    {
        $this->travelTo('2026-08-11 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $customer = Person::create(['category' => 'customer', 'customer_type' => 'business', 'first_name' => 'Solar SL', 'company_name' => 'Solar SL']);
        $installation = Installation::create(['person_id' => $customer->id, 'service_type' => 'solar', 'status' => 'completed', 'site_address_line_1' => 'Calle SLA 1']);
        $policy = ServiceSlaPolicy::create([
            'name' => 'Business normal',
            'priority' => 'normal',
            'customer_type' => 'business',
            'first_response_minutes' => 10,
            'resolution_minutes' => 60,
            'escalation_minutes' => 30,
            'is_active' => true,
        ]);
        $ticket = ServiceTicket::create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'number' => 'SAT-2026-00099',
            'kind' => 'incident',
            'priority' => 'normal',
            'status' => 'open',
            'title' => 'Inverter offline',
        ]);

        $sla = app(SlaManager::class);
        $sla->apply($ticket);
        $this->assertSame($policy->id, $ticket->refresh()->sla_policy_id);
        $this->assertSame('2026-08-11 10:10:00', $ticket->first_response_due_at->format('Y-m-d H:i:s'));

        $ticket->update(['first_response_due_at' => now()->subHour(), 'escalated_at' => now()->subMinutes(10)]);
        $this->assertSame(0, $sla->escalateOverdue());
        $ticket->update(['escalated_at' => now()->subMinutes(31)]);
        $this->assertSame(1, $sla->escalateOverdue());
        $this->assertSame(1, $ticket->refresh()->escalation_level);
        $this->assertCount(1, $admin->refresh()->notifications);
        $this->assertCount(1, $technician->refresh()->notifications);
        $this->assertSame(0, $sla->escalateOverdue());
    }
}
