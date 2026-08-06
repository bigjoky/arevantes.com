<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SerialNumber;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SerialTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_serialized_unit_is_traced_from_supplier_through_transfer_to_installation(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Trace Energy SL', 'trade_name' => 'Trace Energy', 'country' => 'ES']);
        $main = Warehouse::create(['code' => 'MAIN', 'name' => 'Main warehouse']);
        $van = Warehouse::create(['code' => 'VAN-7', 'name' => 'Installer van']);
        $installation = Installation::create([
            'service_type' => 'solar',
            'status' => 'scheduled',
            'project_reference' => 'PRJ-TRACE-1',
            'site_name' => 'Traceability site',
            'site_address_line_1' => 'Calle Solar 1',
        ]);
        $product = Product::create([
            'type' => 'product',
            'sku' => 'INV-TRACE',
            'name' => 'Traceable inverter',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 1200,
            'cost_price' => 800,
            'track_stock' => true,
            'track_serials' => true,
            'is_active' => true,
        ]);

        $this->actingAs($commercial)->post(route('purchases.store'), [
            'type' => 'purchase_order',
            'supplier_id' => $supplier->id,
            'issue_date' => '2026-08-06',
            'lines' => [[
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => 1,
                'unit_price' => 800,
                'discount_percent' => 0,
                'tax_percent' => 21,
            ]],
        ])->assertRedirect();
        $order = PurchaseDocument::where('type', 'purchase_order')->firstOrFail();
        $line = $order->lines()->firstOrFail();

        $this->post(route('purchases.receive', $order), [
            'warehouse_id' => $main->id,
            'supplier_delivery_note' => 'TRACE-DN-1',
            'quantities' => [$line->id => 1],
            'serials' => [$line->id => 'TRACE-SN-001'],
        ])->assertRedirect();

        $this->post(route('inventory.movements.store'), [
            'type' => 'transfer',
            'source_warehouse_id' => $main->id,
            'destination_warehouse_id' => $van->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 1, 'serials' => 'TRACE-SN-001']],
        ])->assertRedirect();

        $delivery = SalesDocument::create([
            'type' => 'delivery_note',
            'installation_id' => $installation->id,
            'created_by' => $commercial->id,
            'number' => 'ALB-TRACE-1',
            'status' => 'draft',
            'issue_date' => '2026-08-06',
            'subtotal' => 1200,
            'discount_amount' => 0,
            'tax_amount' => 252,
            'amount' => 1452,
            'customer_snapshot' => ['first_name' => 'Trace'],
        ]);
        $delivery->lines()->create([
            'product_id' => $product->id,
            'position' => 1,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => 1200,
            'discount_percent' => 0,
            'tax_percent' => 21,
            'subtotal' => 1200,
            'discount_amount' => 0,
            'tax_amount' => 252,
            'total' => 1452,
        ]);

        $this->post(route('sales-documents.dispatch', $delivery), [
            'warehouse_id' => $van->id,
            'serials' => [$product->id => 'TRACE-SN-001'],
        ])->assertRedirect();

        $serial = SerialNumber::where('serial_number', 'TRACE-SN-001')->firstOrFail();
        $this->assertSame('installed', $serial->status);
        $this->assertSame(['received', 'transferred', 'installed'], $serial->events()->pluck('event_type')->all());

        $this->get(route('inventory.serials.index', ['search' => 'TRACE-SN-001']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory/serials/index')
                ->where('serials.total', 1)
                ->where('serials.data.0.status', 'installed')
                ->where('serials.data.0.installation.project_reference', 'PRJ-TRACE-1')
                ->where('serials.data.0.events.0.movement.purchase_document.number', $order->number)
                ->where('serials.data.0.events.0.movement.purchase_document.supplier.trade_name', 'Trace Energy')
                ->where('serials.data.0.events.1.from_warehouse.code', 'MAIN')
                ->where('serials.data.0.events.1.to_warehouse.code', 'VAN-7')
                ->where('serials.data.0.events.2.installation.project_reference', 'PRJ-TRACE-1'));
    }

    public function test_a_technician_can_search_serial_traceability(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);

        $this->actingAs($technician)
            ->get(route('inventory.serials.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('inventory/serials/index'));
    }
}
