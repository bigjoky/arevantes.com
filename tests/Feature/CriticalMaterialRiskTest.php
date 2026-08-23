<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\InstallationMaterialRequirement;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Supply\CriticalMaterialRisk;
use App\Services\Supply\SupplyScenarioPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CriticalMaterialRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_late_critical_spare_blocks_work_and_exposes_the_financial_risk(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-10 08:00:00'));

        $product = Product::create([
            'type' => 'product',
            'sku' => 'CRITICAL-INVERTER',
            'name' => 'Critical inverter',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 800,
            'cost_price' => 500,
            'track_stock' => true,
            'supply_criticality' => 'critical',
            'preferred_lead_time_days' => 10,
            'is_active' => true,
        ]);
        $installation = Installation::create([
            'service_type' => 'solar',
            'status' => 'scheduled',
            'project_reference' => 'PRJ-RISK-1',
            'site_address_line_1' => 'Calle Solar 8',
        ]);
        $workOrder = WorkOrder::create([
            'installation_id' => $installation->id,
            'number' => 'WO-RISK-1',
            'title' => 'Commissioning',
            'status' => 'planned',
            'scheduled_start' => '2026-08-15 09:00:00',
        ]);
        InstallationMaterialRequirement::create([
            'installation_id' => $installation->id,
            'product_id' => $product->id,
            'source' => 'quote',
            'sku' => $product->sku,
            'description' => $product->name,
            'planned_quantity' => 2,
            'planned_unit_cost' => 500,
        ]);
        $supplier = Supplier::create([
            'legal_name' => 'Critical Supply SL',
            'country' => 'ES',
            'is_active' => true,
        ]);
        $purchase = PurchaseDocument::create([
            'type' => 'purchase_order',
            'installation_id' => $installation->id,
            'supplier_id' => $supplier->id,
            'number' => 'PO-RISK-1',
            'status' => 'ordered',
            'issue_date' => '2026-08-10',
            'expected_delivery_date' => '2026-08-20',
            'subtotal' => 900,
            'tax_amount' => 189,
            'amount' => 1089,
        ]);
        $purchase->lines()->create([
            'product_id' => $product->id,
            'position' => 1,
            'description' => $product->name,
            'quantity' => 2,
            'received_quantity' => 0,
            'unit_price' => 450,
            'discount_percent' => 0,
            'tax_percent' => 21,
            'subtotal' => 900,
            'tax_amount' => 189,
            'total' => 1089,
        ]);
        SupplierProduct::updateOrCreate([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
        ], [
            'minimum_order_quantity' => 1,
            'order_multiple' => 1,
            'lead_time_days' => 10,
            'last_unit_price' => 450,
            'currency' => 'EUR',
            'is_preferred' => false,
            'is_active' => true,
        ]);
        $alternative = Supplier::create([
            'legal_name' => 'Fast Alternative SL',
            'country' => 'ES',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);
        SupplierProduct::create([
            'supplier_id' => $alternative->id,
            'product_id' => $product->id,
            'minimum_order_quantity' => 1,
            'order_multiple' => 1,
            'lead_time_days' => 2,
            'last_unit_price' => 550,
            'currency' => 'EUR',
            'is_preferred' => true,
            'is_active' => true,
        ]);

        $risk = app(CriticalMaterialRisk::class)->forecast(CarbonImmutable::parse('2026-08-10'), 45);

        $this->assertSame(1, $risk['summary']['blocked_work_orders']);
        $this->assertSame(1000.0, $risk['summary']['shortage_exposure']);
        $this->assertSame(900.0, $risk['summary']['late_inbound_value']);
        $this->assertSame('blocked', $risk['rows'][0]['severity']);
        $this->assertSame(2.0, $risk['rows'][0]['shortage_quantity']);

        $scenario = app(SupplyScenarioPlanner::class)->forecast(CarbonImmutable::parse('2026-08-10'), 45);
        $this->assertSame($alternative->id, $scenario['rows'][0]['recommended']['supplier_id']);
        $this->assertTrue($scenario['rows'][0]['recommended']['arrives_on_time']);
        $this->assertSame(1100.0, $scenario['scenario']['recommended_net']);
        $this->assertSame(1, $scenario['scenario']['covered_on_time']);

        $commercial = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($commercial)
            ->get(route('operations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('exceptions.0.kind', 'materials')
                ->where('exceptions.0.severity', 'critical')
                ->where('exceptions.0.href', '/work-orders?work_order='.$workOrder->id));

        $technician = User::factory()->create(['role' => 'technician']);
        $workOrder->update(['assigned_user_id' => $technician->id]);
        $this->actingAs($technician)
            ->get(route('supply-control.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('materialRisk.summary.shortage_exposure', null)
                ->where('materialRisk.rows.0.shortage_exposure', null)
                ->where('materialRisk.rows.0.recommended.net_cost', null));

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('finance.index', ['year' => 2026]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('supplyScenario.scenario.recommended_net', 1100)
                ->where('supplyScenario.rows.0.recommended.supplier_id', $alternative->id));
    }
}
