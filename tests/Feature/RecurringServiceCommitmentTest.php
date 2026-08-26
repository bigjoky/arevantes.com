<?php

namespace Tests\Feature;

use App\Models\FinancialForecastItem;
use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\ReplenishmentTarget;
use App\Models\ServiceCycleCommitment;
use App\Models\ServiceTicket;
use App\Models\StockReservation;
use App\Models\TaxRate;
use App\Models\TimeEntry;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\AfterSales\MaintenancePlanLifecycle;
use App\Services\Analytics\RecurringServiceEconomics;
use App\Services\Planning\RecurringServiceCapacityForecast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RecurringServiceCommitmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_capacity_and_cycle_economics_are_approved_idempotently_against_one_plan_version(): void
    {
        $this->travelTo('2026-08-26 10:00:00');
        config(['work.default_hourly_cost' => 60, 'work.mileage_rate' => .26]);
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create(['technician_user_id' => $technician->id, 'service_type' => 'security', 'status' => 'completed', 'project_reference' => 'SEG-CYCLE', 'site_address_line_1' => 'Calle Ciclo 1']);
        $plan = app(MaintenancePlanLifecycle::class)->create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'name' => 'Security response cycle',
            'service_level' => 'critical',
            'coverage_type' => 'full',
            'interval_months' => 3,
            'starts_on' => today(),
            'next_due_on' => today()->addWeek(),
            'billing_frequency_months' => 3,
            'billing_amount' => 300,
            'next_billing_on' => today()->addWeek(),
        ], [], $admin);
        $product = $this->stockProduct('BAT-CYCLE', 'Alarm battery', 10);
        $plan->preventiveMaterials()->create(['product_id' => $product->id, 'quantity_per_visit' => 2, 'expected_usage_percent' => 50, 'minimum_stock' => 0]);

        $this->assertSame(1, app(RecurringServiceCapacityForecast::class)->forecast(today(), 6, $admin)['summary']['due_plans']);

        $payload = ['plan_version' => 1, 'planned_travel_km' => 10, 'approval_note' => 'Technician, capacity and planned costs reviewed.'];
        $this->actingAs($admin)->post(route('after-sales.plans.commitments.store', $plan), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('after-sales.plans.commitments.store', $plan), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $commitment = ServiceCycleCommitment::sole();
        $this->assertSame(1, $commitment->maintenance_plan_version);
        $this->assertSame('300.00', $commitment->planned_revenue);
        $this->assertSame('90.00', $commitment->planned_labor_cost);
        $this->assertSame('10.00', $commitment->planned_material_cost);
        $this->assertSame('2.60', $commitment->planned_travel_cost);
        $this->assertSame($technician->id, $commitment->capacity_snapshot['assigned_user_id']);
        $this->assertSame(0, WorkOrder::count());
        $this->assertSame(0, StockReservation::count());
        $this->assertSame(0, app(RecurringServiceCapacityForecast::class)->forecast(today(), 6, $admin)['summary']['due_plans']);
        $this->actingAs($technician)->get(route('after-sales.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('plans.0.cycle_commitments.0.status', 'approved')
                ->missing('plans.0.billing_amount')
                ->missing('plans.0.cycle_commitments.0.planned_revenue')
                ->where('metrics.annual_recurring_value', null)
                ->where('serviceEconomics.summary', null));

        $forecast = FinancialForecastItem::where('maintenance_plan_id', $plan->id)->where('maintenance_plan_version', 1)->sole();
        $forecast->update(['status' => 'settled', 'actual_amount' => 300, 'settled_on' => $plan->next_due_on]);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-CYCLE-1', 'title' => 'Security cycle', 'kind' => 'maintenance', 'status' => 'completed']);
        $ticket = ServiceTicket::create(['installation_id' => $installation->id, 'maintenance_plan_id' => $plan->id, 'maintenance_plan_version' => 1, 'work_order_id' => $order->id, 'number' => 'SAT-CYCLE-1', 'kind' => 'maintenance', 'priority' => 'high', 'status' => 'resolved', 'title' => 'Security cycle']);
        $ticket->timestamps = false;
        $ticket->forceFill(['created_at' => $plan->next_due_on->copy()->addDay()])->saveQuietly();
        TimeEntry::create(['work_order_id' => $order->id, 'user_id' => $technician->id, 'started_at' => $plan->next_due_on, 'ended_at' => $plan->next_due_on->copy()->addHour(), 'duration_minutes' => 60, 'hourly_cost' => 50, 'travel_km' => 10, 'mileage_rate' => .5]);

        $economics = app(RecurringServiceEconomics::class)->build(true);
        $this->assertSame(300.0, $economics['summary']['settled_revenue']);
        $this->assertSame(55.0, $economics['summary']['actual_cost']);
        $this->assertSame(245.0, $economics['summary']['actual_margin']);
        $this->actingAs($admin)->get(route('analytics.index', ['area' => 'executive']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('recurringEconomics.summary.cycles', 1)
                ->where('recurringEconomics.summary.actual_margin', 245));
    }

    public function test_preventive_shortage_becomes_an_approved_objective_without_stock_or_purchase_commitment(): void
    {
        $this->travelTo('2026-08-26 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create(['service_type' => 'electrical', 'status' => 'completed', 'project_reference' => 'ELEC-TARGET', 'site_address_line_1' => 'Calle Objetivo 1']);
        $plan = MaintenancePlan::create(['installation_id' => $installation->id, 'name' => 'Electrical preventive cycle', 'version' => 2, 'interval_months' => 3, 'starts_on' => today(), 'next_due_on' => today()->addWeek(), 'is_active' => true]);
        $product = $this->stockProduct('FUSE-TARGET', 'Service fuse', 4);
        $plan->preventiveMaterials()->create(['product_id' => $product->id, 'quantity_per_visit' => 1, 'expected_usage_percent' => 100, 'minimum_stock' => 3]);

        $payload = ['target_quantity' => 5, 'approval_note' => 'Preventive demand and available supply reviewed.'];
        $this->actingAs($admin)->post(route('replenishment-targets.store', $product), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('replenishment-targets.store', $product), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $target = ReplenishmentTarget::sole();
        $this->assertSame('5.000', $target->target_quantity);
        $this->assertSame('5.000', $target->forecast_shortage);
        $this->assertSame(2, $target->source_snapshot['plans'][0]['plan_version']);
        $this->assertSame(0, StockReservation::count());
        $this->assertSame(0, PurchaseDocument::count());
        $this->actingAs($technician)->post(route('replenishment-targets.store', $product), $payload)->assertForbidden();
        $this->actingAs($admin)->get(route('supply-control.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('replenishmentTargets.0.product_id', $product->id)
                ->where('replenishmentTargets.0.target_quantity', '5.000'));
    }

    public function test_unassigned_or_stale_service_cycle_cannot_be_approved(): void
    {
        $this->travelTo('2026-08-26 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'technician']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'completed', 'project_reference' => 'FV-GUARD', 'site_address_line_1' => 'Calle Guardia 1']);
        $plan = MaintenancePlan::create(['installation_id' => $installation->id, 'name' => 'Unassigned cycle', 'version' => 2, 'service_level' => 'critical', 'interval_months' => 3, 'starts_on' => today(), 'next_due_on' => today()->addWeek(), 'is_active' => true]);

        $this->actingAs($admin)->post(route('after-sales.plans.commitments.store', $plan), ['plan_version' => 1, 'planned_travel_km' => 0, 'approval_note' => 'Attempt without an assigned owner.'])
            ->assertSessionHasErrors('commitment');
        $this->assertSame(0, ServiceCycleCommitment::count());
    }

    private function stockProduct(string $sku, string $name, float $cost): Product
    {
        return Product::create([
            'type' => 'product', 'sku' => $sku, 'name' => $name,
            'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => $cost * 2, 'cost_price' => $cost, 'track_stock' => true,
            'track_serials' => false, 'is_active' => true,
        ]);
    }
}
