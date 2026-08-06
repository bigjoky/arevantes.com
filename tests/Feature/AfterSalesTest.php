<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\ServiceTicket;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AfterSalesTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertSame(today()->addMonths(12)->toDateString(), $plan->refresh()->next_due_on->toDateString());

        $product = Product::create(['type' => 'product', 'name' => 'Inverter', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 1000, 'cost_price' => 700, 'track_stock' => true, 'track_serials' => true, 'is_active' => true]);
        $serial = SerialNumber::create(['product_id' => $product->id, 'installation_id' => $installation->id, 'serial_number' => 'INV-WARRANTY-1', 'status' => 'installed']);
        $this->patch(route('after-sales.warranties.update', $serial), ['warranty_starts_on' => '2026-07-01', 'warranty_ends_on' => '2036-07-01'])->assertRedirect();
        $this->assertSame('2036-07-01', $serial->refresh()->warranty_ends_on->toDateString());
    }
}
