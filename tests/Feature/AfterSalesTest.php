<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\Person;
use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\ServiceSlaPolicy;
use App\Models\ServiceTicket;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\AfterSales\SlaManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AfterSalesTest extends TestCase
{
    use RefreshDatabase;

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
        ])->assertRedirect()->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame('Complete inverter maintenance', $plan->name);
        $this->assertSame($technician->id, $plan->assigned_user_id);
        $this->assertSame(6, $plan->interval_months);
        $this->assertSame(21, $plan->lead_days);
        $this->assertSame('2026-08-01', $plan->next_due_on->toDateString());
        $this->assertTrue($plan->generate_work_order);
        $this->assertSame('Inspect protections, firmware and ventilation.', $plan->scope);

        $this->patch(route('after-sales.plans.update', $plan), ['is_active' => false])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($plan->refresh()->is_active);
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
