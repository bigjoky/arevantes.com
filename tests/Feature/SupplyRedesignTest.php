<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\Installation;
use App\Models\InstallationMaterialRequirement;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\SerialNumber;
use App\Models\StockMovement;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\StockPositionService;
use App\Services\Inventory\WorkOrderMaterialConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplyRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function product(bool $serial = false): Product
    {
        return Product::create(['type' => 'product', 'sku' => Str::random(12), 'name' => 'Material de prueba', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 120, 'cost_price' => 75, 'track_stock' => true, 'track_serials' => $serial, 'is_active' => true]);
    }

    private function installation(): Installation
    {
        return Installation::create(['service_type' => 'solar', 'status' => 'approved', 'project_reference' => 'PRJ-TEST', 'site_address_line_1' => 'Calle prueba']);
    }

    public function test_parent_and_catalog_use_real_stock_and_backend_filters(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $product = $this->product();
        $warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Almacén']);
        app(StockLedger::class)->post(['type' => 'receipt', 'warehouse_id' => $warehouse->id, 'lines' => [['product_id' => $product->id, 'quantity' => 2.5, 'unit_cost' => 70]]], $user->id);
        $this->actingAs($user)->get('/supply')->assertOk()->assertInertia(fn (Assert $page) => $page->component('supply/dashboard')->where('metrics.in_stock', 1)->where('metrics.consumption', 0));
        $this->get('/catalog?search='.$product->sku)->assertInertia(fn (Assert $page) => $page->component('supply/products')->has('products.data', 1)->where('products.data.0.physical', 2.5));
        $this->get('/catalog?search=nonexistent')->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
        $this->get('/catalog/'.$product->id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('supply/product')->where('product.id', $product->id));
    }

    public function test_partial_receipt_retries_do_not_duplicate_stock(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $product = $this->product();
        $warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Almacén']);
        $supplier = Supplier::create(['legal_name' => 'Proveedor de prueba', 'country' => 'ES']);
        $this->actingAs($user)->post('/purchases', ['type' => 'purchase_order', 'supplier_id' => $supplier->id, 'issue_date' => today()->toDateString(), 'lines' => [['product_id' => $product->id, 'description' => $product->name, 'quantity' => 10, 'unit_price' => 70, 'tax_percent' => 21, 'discount_percent' => 0]]])->assertRedirect()->assertSessionHasNoErrors();
        $order = PurchaseDocument::where('type', 'purchase_order')->firstOrFail();
        $this->assertSame(0, StockMovement::count());
        $line = $order->lines()->firstOrFail();
        $input = ['operation_key' => (string) Str::uuid(), 'warehouse_id' => $warehouse->id, 'quantities' => [$line->id => 4]];
        $this->post('/purchases/'.$order->id.'/receive', $input)->assertRedirect();
        $this->post('/purchases/'.$order->id.'/receive', $input)->assertRedirect();
        $this->assertSame(1, GoodsReceipt::count());
        $this->assertSame(4.0, (float) StockMovementLine::sum('quantity'));
        $this->assertSame('partially_received', $order->fresh()->status);
        $this->post('/purchases/'.$order->id.'/receive', [...$input, 'operation_key' => (string) Str::uuid(), 'quantities' => [$line->id => 6]])->assertRedirect();
        $this->assertSame(2, GoodsReceipt::count());
        $this->assertSame('received', $order->fresh()->status);
        $this->assertSame(10.0, (float) StockMovementLine::sum('quantity'));
    }

    public function test_delivery_moves_reservations_and_return_does_not_create_consumption(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $product = $this->product();
        $installation = $this->installation();
        $source = Warehouse::create(['code' => 'MAIN', 'name' => 'Origen', 'is_active' => true]);
        $destination = Warehouse::create(['code' => 'SITE', 'name' => 'Destino real', 'is_active' => true]);
        app(StockLedger::class)->post(['type' => 'receipt', 'warehouse_id' => $source->id, 'lines' => [['product_id' => $product->id, 'quantity' => 10]]], $user->id);
        $this->actingAs($user)->post('/inventory/reservations', ['installation_id' => $installation->id, 'warehouse_id' => $source->id, 'product_id' => $product->id, 'quantity' => 8])->assertRedirect();
        $input = ['type' => 'delivery', 'operation_key' => (string) Str::uuid(), 'installation_id' => $installation->id, 'source_warehouse_id' => $source->id, 'destination_warehouse_id' => $destination->id, 'product_id' => $product->id, 'quantity' => 6, 'material_condition' => 'usable'];
        $this->post('/inventory/transport', $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/inventory/transport', $input)->assertRedirect();
        $delivery = StockMovement::where('type', 'delivery')->firstOrFail();
        $this->assertSame(1, StockMovement::where('type', 'delivery')->count());
        $this->assertSame(0, StockMovement::where('type', 'issue')->count());
        $this->assertSame(10.0, (float) StockMovementLine::sum('quantity'));
        $this->assertSame(6.0, (float) StockReservation::where('warehouse_id', $destination->id)->where('status', 'active')->sum('quantity'));
        $this->post('/inventory/transport', [...$input, 'operation_key' => (string) Str::uuid(), 'type' => 'internal_return', 'source_movement_id' => $delivery->id, 'source_warehouse_id' => $destination->id, 'destination_warehouse_id' => $source->id, 'quantity' => 2])->assertRedirect();
        $this->assertSame(10.0, (float) StockMovementLine::sum('quantity'));
        $this->assertSame(1, StockMovement::where('type', 'internal_return')->count());
        $this->post('/inventory/transport', [...$input, 'operation_key' => (string) Str::uuid(), 'type' => 'internal_return', 'source_movement_id' => $delivery->id, 'source_warehouse_id' => $destination->id, 'destination_warehouse_id' => $source->id, 'quantity' => 5])->assertSessionHasErrors('quantity');
    }

    public function test_damaged_returns_stay_physical_but_cannot_be_reserved_or_consumed(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $product = $this->product();
        $installation = $this->installation();
        $source = Warehouse::create(['code' => 'MAIN', 'name' => 'Origen', 'is_active' => true]);
        $site = Warehouse::create(['code' => 'SITE', 'name' => 'Obra real', 'is_active' => true]);
        $quarantine = Warehouse::create(['code' => 'HOLD', 'name' => 'Cuarentena real', 'is_active' => true, 'is_usable' => false]);
        app(StockLedger::class)->post(['type' => 'receipt', 'warehouse_id' => $source->id, 'lines' => [['product_id' => $product->id, 'quantity' => 2]]], $user->id);
        $input = ['type' => 'delivery', 'operation_key' => (string) Str::uuid(), 'installation_id' => $installation->id, 'source_warehouse_id' => $source->id, 'destination_warehouse_id' => $site->id, 'product_id' => $product->id, 'quantity' => 2, 'material_condition' => 'usable'];
        $this->actingAs($user)->post('/inventory/transport', $input)->assertRedirect();
        $delivery = StockMovement::where('type', 'delivery')->firstOrFail();
        $this->post('/inventory/transport', [...$input, 'operation_key' => (string) Str::uuid(), 'type' => 'internal_return', 'source_movement_id' => $delivery->id, 'source_warehouse_id' => $site->id, 'destination_warehouse_id' => $quarantine->id, 'material_condition' => 'damaged'])->assertRedirect();
        $position = app(StockPositionService::class)->positions()->firstWhere('warehouse.id', $quarantine->id);
        $this->assertSame(2.0, $position['on_hand']);
        $this->assertSame(0, $position['available']);
        $this->post('/inventory/reservations', ['installation_id' => $installation->id, 'warehouse_id' => $quarantine->id, 'product_id' => $product->id, 'quantity' => 1])->assertSessionHasErrors('warehouse_id');
        $this->post('/inventory/movements', ['type' => 'issue', 'warehouse_id' => $quarantine->id, 'lines' => [['product_id' => $product->id, 'quantity' => 1]]])->assertSessionHasErrors('lines');
    }

    public function test_technician_receipt_surfaces_do_not_send_financial_data(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $product = $this->product();
        $supplier = Supplier::create(['legal_name' => 'Proveedor prueba', 'country' => 'ES']);
        $order = PurchaseDocument::create(['type' => 'purchase_order', 'supplier_id' => $supplier->id, 'created_by' => $user->id, 'number' => 'TEST-PO', 'status' => 'ordered', 'issue_date' => today(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121]);
        $order->lines()->create(['product_id' => $product->id, 'position' => 1, 'description' => $product->name, 'quantity' => 1, 'unit_price' => 100, 'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 100, 'tax_amount' => 21, 'total' => 121]);
        $this->actingAs($user)->get('/purchases/'.$order->id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('supply/order')->where('order.amount', null)->where('lines.0.unit_price', null)->where('capabilities.receive', true));
        $this->get('/purchases')->assertOk()->assertInertia(fn (Assert $page) => $page->where('orders.data.0.amount', null)->where('products', []));
        $this->get('/catalog')->assertForbidden();
        $this->post('/inventory/transport', [])->assertForbidden();
    }

    public function test_receipt_key_cannot_be_reused_for_changed_quantities(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $product = $this->product();
        $warehouse = Warehouse::create(['code' => 'M', 'name' => 'Main']);
        $supplier = Supplier::create(['legal_name' => 'Supplier', 'country' => 'ES']);
        $order = PurchaseDocument::create(['type' => 'purchase_order', 'supplier_id' => $supplier->id, 'created_by' => $user->id, 'number' => 'PO-KEY', 'status' => 'ordered', 'issue_date' => today(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121]);
        $line = $order->lines()->create(['product_id' => $product->id, 'position' => 1, 'description' => 'Material', 'quantity' => 5, 'unit_price' => 20, 'tax_percent' => 21, 'subtotal' => 100, 'tax_amount' => 21, 'total' => 121]);
        $input = ['operation_key' => (string) Str::uuid(), 'warehouse_id' => $warehouse->id, 'quantities' => [$line->id => 1]];
        $this->actingAs($user)->post('/purchases/'.$order->id.'/receive', $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/purchases/'.$order->id.'/receive', [...$input, 'quantities' => [$line->id => 2]])->assertStatus(409);
        $this->assertSame(1, GoodsReceipt::count());
        $this->assertSame(1.0, (float) StockMovementLine::sum('quantity'));
    }

    public function test_serialized_delivery_consumption_retry_and_return_preserve_trace(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $product = $this->product(true);
        $installation = $this->installation();
        $source = Warehouse::create(['code' => 'M', 'name' => 'Main', 'is_active' => true]);
        $site = Warehouse::create(['code' => 'S', 'name' => 'Site', 'is_active' => true]);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'created_by' => $user->id, 'number' => 'WO-SERIAL', 'title' => 'Installation', 'kind' => 'installation', 'status' => 'in_progress']);
        app(StockLedger::class)->post(['type' => 'receipt', 'warehouse_id' => $source->id, 'lines' => [['product_id' => $product->id, 'quantity' => 2, 'serials' => "SN-A\nSN-B", 'unit_cost' => 70]]], $user->id);
        StockReservation::create(['work_order_id' => $order->id, 'installation_id' => $installation->id, 'warehouse_id' => $source->id, 'product_id' => $product->id, 'quantity' => 2, 'status' => 'active']);
        $input = ['type' => 'delivery', 'operation_key' => (string) Str::uuid(), 'installation_id' => $installation->id, 'recipient_id' => $user->id, 'source_warehouse_id' => $source->id, 'destination_warehouse_id' => $site->id, 'product_id' => $product->id, 'quantity' => 2, 'serials' => "SN-A\nSN-B", 'material_condition' => 'usable'];
        $this->actingAs($user)->post('/inventory/transport', $input)->assertRedirect()->assertSessionHasNoErrors();
        $delivery = StockMovement::where('type', 'delivery')->firstOrFail();
        $reservation = StockReservation::where('warehouse_id', $site->id)->firstOrFail();
        $key = (string) Str::uuid();
        $consumer = app(WorkOrderMaterialConsumer::class);
        $consumer->consume($order, $reservation, 1, 'SN-A', $user->id, $key);
        $product->update(['cost_price' => 999]);
        $consumer->consume($order, $reservation, 1, 'SN-A', $user->id, $key);
        $this->assertSame(1, StockMovement::where('type', 'issue')->count());
        $this->assertSame(1.0, (float) $reservation->fresh()->consumed_quantity);
        $this->assertSame(75.0, (float) StockMovementLine::whereHas('movement', fn ($q) => $q->where('type', 'issue'))->value('unit_cost'));
        $serial = SerialNumber::where('serial_number', 'SN-A')->firstOrFail();
        $this->assertSame($installation->id, $serial->installation_id);
        $this->post('/inventory/transport', [...$input, 'type' => 'internal_return', 'operation_key' => (string) Str::uuid(), 'source_movement_id' => $delivery->id, 'source_warehouse_id' => $site->id, 'destination_warehouse_id' => $source->id, 'quantity' => 1, 'serials' => 'SN-B'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($source->id, SerialNumber::where('serial_number', 'SN-B')->value('warehouse_id'));
        $this->assertSame(1.0, (float) StockMovementLine::sum('quantity'));
        app(StockLedger::class)->post(['type' => 'receipt', 'warehouse_id' => $site->id, 'lines' => [['product_id' => $product->id, 'quantity' => 1, 'serials' => 'UNRELATED']]], $user->id);
        $this->post('/inventory/transport', [...$input, 'type' => 'internal_return', 'operation_key' => (string) Str::uuid(), 'source_movement_id' => $delivery->id, 'source_warehouse_id' => $site->id, 'destination_warehouse_id' => $source->id, 'quantity' => 1, 'serials' => 'UNRELATED'])->assertSessionHasErrors('serials');
        $this->assertSame(1, StockMovement::where('type', 'internal_return')->count());
    }

    public function test_needs_combine_sources_without_multiplying_reservations(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $product = $this->product();
        $installation = $this->installation();
        $warehouse = Warehouse::create(['code' => 'M', 'name' => 'Main']);
        app(StockLedger::class)->post(['type' => 'receipt', 'warehouse_id' => $warehouse->id, 'lines' => [['product_id' => $product->id, 'quantity' => 4]]], $user->id);
        InstallationMaterialRequirement::create(['installation_id' => $installation->id, 'product_id' => $product->id, 'source' => 'quote', 'description' => 'Quoted material', 'planned_quantity' => 4, 'planned_unit_cost' => 70]);
        StockReservation::create(['installation_id' => $installation->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 2, 'status' => 'active']);
        $input = ['installation_id' => $installation->id, 'product_id' => $product->id, 'planned_quantity' => 3];
        $this->actingAs($user)->post('/inventory/needs', $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/inventory/needs', $input)->assertSessionHasErrors('product_id');
        $this->get('/inventory?view=needs')->assertOk()->assertInertia(fn (Assert $page) => $page->has('needs.data', 1)->where('needs.data.0.reserved', 2)->where('needs.data.0.planned_quantity', '7.000')->where('needs.data.0.available', 2)->where('needs.data.0.shortage', 3)->missing('needs.data.0.planned_unit_cost'));
    }
}
