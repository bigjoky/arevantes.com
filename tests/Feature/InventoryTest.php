<?php

namespace Tests\Feature;

use App\Models\FieldServiceProfile;
use App\Models\Installation;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SerialNumber;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use App\Services\Work\WorkOrderFactory;
use App\Services\Work\WorkOrderPlanningReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_sections_have_independent_authorized_destinations(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        foreach (['stock' => '/inventory', 'reservations' => '/inventory/reservations', 'movements' => '/inventory/movements', 'warehouses' => '/inventory/warehouses'] as $section => $url) {
            $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component($section === 'stock' ? 'supply/inventory' : 'inventory/index')->where($section === 'stock' ? 'view' : 'initialSection', $section));
            $this->actingAs(User::factory()->create(['role' => 'technician']))->get($url)->assertForbidden();
        }
    }

    public function test_serialized_stock_can_be_received_transferred_and_issued(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $source = Warehouse::create(['code' => 'MAIN', 'name' => 'Main warehouse', 'is_default' => true]);
        $destination = Warehouse::create(['code' => 'VAN-1', 'name' => 'Service van']);
        $product = $this->product(true);
        $this->actingAs($user);

        $this->post(route('inventory.movements.store'), [
            'type' => 'receipt', 'warehouse_id' => $source->id, 'reference' => 'PO-1',
            'lines' => [['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 500, 'serials' => 'INV-001, INV-002']],
        ])->assertRedirect();
        $this->assertSame('2', (string) StockMovementLine::where('warehouse_id', $source->id)->sum('quantity'));
        $this->assertSame('2.000', StockBalance::where('warehouse_id', $source->id)->where('product_id', $product->id)->value('quantity'));
        $this->assertSame(2, SerialNumber::where('status', 'available')->count());

        $this->post(route('inventory.movements.store'), [
            'type' => 'transfer', 'source_warehouse_id' => $source->id, 'destination_warehouse_id' => $destination->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 1, 'serials' => 'INV-001']],
        ])->assertRedirect();
        $this->assertSame($destination->id, SerialNumber::where('serial_number', 'INV-001')->value('warehouse_id'));
        $this->assertSame('1.000', StockBalance::where('warehouse_id', $source->id)->where('product_id', $product->id)->value('quantity'));
        $this->assertSame('1.000', StockBalance::where('warehouse_id', $destination->id)->where('product_id', $product->id)->value('quantity'));

        $this->post(route('inventory.movements.store'), [
            'type' => 'issue', 'warehouse_id' => $destination->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 1, 'serials' => 'INV-001']],
        ])->assertRedirect();
        $serial = SerialNumber::where('serial_number', 'INV-001')->firstOrFail();
        $this->assertSame('issued', $serial->status);
        $this->assertNull($serial->warehouse_id);
        $this->assertSame('0.000', StockBalance::where('warehouse_id', $destination->id)->where('product_id', $product->id)->value('quantity'));
    }

    public function test_an_insufficient_stock_movement_is_fully_rolled_back(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Main warehouse']);
        $product = $this->product(false);

        $this->actingAs($user)->post(route('inventory.movements.store'), [
            'type' => 'issue', 'warehouse_id' => $warehouse->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('lines');

        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, StockMovementLine::count());
        $this->assertSame(0, StockBalance::count());
    }

    public function test_stock_position_distinguishes_on_hand_reservations_availability_and_transit(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Inbound Supply SL', 'country' => 'ES']);
        $warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Main warehouse']);
        $product = $this->product(false);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Stock 1']);
        $this->actingAs($user)->post(route('inventory.movements.store'), [
            'type' => 'receipt',
            'warehouse_id' => $warehouse->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 500]],
        ])->assertRedirect();
        $this->post(route('inventory.reservations.store'), [
            'installation_id' => $installation->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertRedirect();

        $order = PurchaseDocument::create([
            'type' => 'purchase_order',
            'supplier_id' => $supplier->id,
            'destination_warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'number' => 'PC-STOCK-1',
            'status' => 'ordered',
            'issue_date' => '2026-08-01',
            'expected_delivery_date' => '2026-08-10',
            'subtotal' => 2500,
            'tax_amount' => 525,
            'amount' => 3025,
        ]);
        $line = $order->lines()->create([
            'product_id' => $product->id,
            'position' => 1,
            'description' => $product->name,
            'quantity' => 5,
            'unit_price' => 500,
            'discount_percent' => 0,
            'tax_percent' => 21,
            'subtotal' => 2500,
            'tax_amount' => 525,
            'total' => 3025,
        ]);
        $this->post(route('purchases.receive', $order), [
            'warehouse_id' => $warehouse->id,
            'quantities' => [$line->id => 2],
        ])->assertRedirect();

        $this->get(route('inventory.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('supply/inventory')
                ->has('positions.data', 1)
                ->where('positions.data.0.on_hand', 12)
                ->where('positions.data.0.reserved', 3)
                ->where('positions.data.0.available', 9)
                ->where('positions.data.0.in_transit', 3)
                ->where('positions.data.0.projected', 12)
                ->where('positions.data.0.inbound_order_count', 1)
                ->where('positions.data.0.next_delivery_date', '2026-08-10'));
    }

    public function test_a_reserved_serialized_item_is_dispatched_to_its_installation(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Main warehouse']);
        $product = $this->product(true);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Calle Sol 1']);
        $this->actingAs($user)->post(route('inventory.movements.store'), [
            'type' => 'receipt', 'warehouse_id' => $warehouse->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 1, 'serials' => 'INV-100']],
        ])->assertRedirect();
        $this->post(route('inventory.reservations.store'), [
            'installation_id' => $installation->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 1,
        ])->assertRedirect();

        $delivery = SalesDocument::create([
            'type' => 'delivery_note', 'installation_id' => $installation->id, 'created_by' => $user->id,
            'number' => 'ALB-2026-00001', 'status' => 'draft', 'issue_date' => '2026-07-12',
            'subtotal' => 1000, 'discount_amount' => 0, 'tax_amount' => 210, 'amount' => 1210,
            'customer_snapshot' => ['first_name' => 'Customer'],
        ]);
        $delivery->lines()->create(['product_id' => $product->id, 'position' => 1, 'description' => 'Inverter', 'quantity' => 1, 'unit_price' => 1000, 'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 1000, 'discount_amount' => 0, 'tax_amount' => 210, 'total' => 1210]);

        $this->post(route('sales-documents.dispatch', $delivery), [
            'warehouse_id' => $warehouse->id, 'serials' => [$product->id => 'INV-100'],
        ])->assertRedirect();

        $this->assertSame('delivered', $delivery->refresh()->status);
        $this->assertSame('fulfilled', StockReservation::firstOrFail()->status);
        $serial = SerialNumber::where('serial_number', 'INV-100')->firstOrFail();
        $this->assertSame('installed', $serial->status);
        $this->assertSame($installation->id, $serial->installation_id);
        $this->assertDatabaseHas('stock_movements', ['sales_document_id' => $delivery->id, 'installation_id' => $installation->id]);
    }

    public function test_work_order_materials_can_be_reserved_and_consumed_partially(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        $otherTechnician = User::factory()->create(['role' => 'technician']);
        $warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Main warehouse']);
        $product = $this->product(false);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 2']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'created_by' => $commercial->id, 'number' => 'WO-2026-00100', 'title' => 'Install solar array', 'kind' => 'installation']);
        $installation->materialRequirements()->create([
            'product_id' => $product->id,
            'source' => 'quote',
            'sku' => $product->sku,
            'description' => $product->name,
            'planned_quantity' => 8,
            'planned_unit_cost' => $product->cost_price,
        ]);

        $this->actingAs($commercial)->post(route('inventory.movements.store'), [
            'type' => 'receipt',
            'warehouse_id' => $warehouse->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 2]],
        ])->assertRedirect();
        $this->post(route('work-orders.materials.reserve', $order), [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 8,
        ])->assertRedirect();
        $reservation = StockReservation::firstOrFail();
        $this->assertSame($order->id, $reservation->work_order_id);

        $this->actingAs($otherTechnician)->post(route('work-orders.materials.consume', [$order, $reservation]), ['quantity' => 1])->assertForbidden();
        $this->actingAs($technician)->post(route('work-orders.materials.consume', [$order, $reservation]), ['quantity' => 3])->assertRedirect();
        $this->assertSame('3.000', $reservation->refresh()->consumed_quantity);
        $this->assertSame('active', $reservation->status);
        $this->assertDatabaseHas('stock_movements', ['work_order_id' => $order->id, 'installation_id' => $installation->id, 'type' => 'issue']);
        $this->assertSame(7.0, (float) StockMovementLine::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->sum('quantity'));

        $this->post(route('work-orders.materials.consume', [$order, $reservation]), ['quantity' => 6])->assertSessionHasErrors('quantity');
        $this->post(route('work-orders.materials.consume', [$order, $reservation]), ['quantity' => 5])->assertRedirect();
        $this->assertSame('consumed', $reservation->refresh()->status);
        $this->assertSame('8.000', $reservation->consumed_quantity);
        $this->assertSame(2.0, (float) StockMovementLine::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->sum('quantity'));
        $this->assertSame('500.00', StockMovementLine::where('product_id', $product->id)->where('quantity', '<', 0)->firstOrFail()->unit_cost);
        $this->get(route('work-orders.manage', ['work_order' => $order->id]))
            ->assertInertia(fn (Assert $page) => $page->where('selected.material_plan.planned_cost', null)->where('selected.material_plan.rows.0.actual_quantity', 8));
        $this->actingAs($commercial)->get(route('work-orders.manage', ['work_order' => $order->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected.material_plan.planned_cost', 4000)
                ->where('selected.material_plan.actual_cost', 4000)
                ->where('selected.material_plan.variance', 0)
                ->where('selected.material_plan.rows.0.actual_quantity', 8));
    }

    public function test_a_project_shortage_creates_one_traceable_purchase_request(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Solar Supply SL', 'country' => 'ES', 'payment_terms_days' => 30, 'is_active' => true]);
        $warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Main warehouse']);
        $product = $this->product(false);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 3']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'created_by' => $commercial->id, 'number' => 'WO-2026-00101', 'title' => 'Install solar array', 'kind' => 'installation']);
        $installation->materialRequirements()->create([
            'product_id' => $product->id,
            'source' => 'quote',
            'sku' => $product->sku,
            'description' => $product->name,
            'planned_quantity' => 10,
            'planned_unit_cost' => $product->cost_price,
        ]);

        $this->actingAs($commercial)->post(route('inventory.movements.store'), [
            'type' => 'receipt',
            'warehouse_id' => $warehouse->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 3, 'unit_cost' => 500]],
        ])->assertRedirect();
        $this->get(route('work-orders.manage', ['work_order' => $order->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected.material_plan.rows.0.available_quantity', 3)
                ->where('selected.material_plan.rows.0.shortage_quantity', 7)
                ->where('selected.material_plan.shortage_items', 1));

        $this->post(route('work-orders.materials.procure', $order), ['supplier_id' => $supplier->id])->assertRedirect();
        $request = PurchaseDocument::where('type', 'purchase_rfq')->firstOrFail();
        $this->assertSame($installation->id, $request->installation_id);
        $this->assertSame($order->id, $request->work_order_id);
        $this->assertSame('7.000', $request->lines()->firstOrFail()->quantity);

        $this->post(route('purchases.convert', $request))->assertRedirect();
        $purchaseOrder = PurchaseDocument::where('type', 'purchase_order')->firstOrFail();
        $this->assertSame($installation->id, $purchaseOrder->installation_id);
        $this->assertSame($order->id, $purchaseOrder->work_order_id);
        $this->get(route('work-orders.manage', ['work_order' => $order->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected.material_plan.rows.0.inbound_quantity', 7)
                ->where('selected.material_plan.rows.0.shortage_quantity', 0)
                ->has('selected.purchase_documents', 2));

        $this->post(route('work-orders.materials.procure', $order), ['supplier_id' => $supplier->id])
            ->assertSessionHasErrors('materials');
        $this->assertSame(1, PurchaseDocument::where('type', 'purchase_rfq')->count());

        $purchaseLine = $purchaseOrder->lines()->firstOrFail();
        $this->post(route('purchases.receive', $purchaseOrder), [
            'warehouse_id' => $warehouse->id,
            'quantities' => [$purchaseLine->id => 7],
        ])->assertRedirect();
        $reservation = StockReservation::where('work_order_id', $order->id)->firstOrFail();
        $this->assertSame('7.000', $reservation->quantity);
        $this->assertSame('received', $purchaseOrder->refresh()->status);
        $this->get(route('work-orders.manage', ['work_order' => $order->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected.material_plan.rows.0.available_quantity', 3)
                ->where('selected.material_plan.rows.0.reserved_quantity', 7)
                ->where('selected.material_plan.rows.0.inbound_quantity', 0)
                ->where('selected.material_plan.rows.0.shortage_quantity', 0));
    }

    private function product(bool $serialized): Product
    {
        return Product::create([
            'type' => 'product', 'sku' => $serialized ? 'SERIAL' : 'BULK', 'name' => $serialized ? 'Inverter' : 'Cable',
            'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 1000, 'cost_price' => 500, 'track_stock' => true, 'track_serials' => $serialized, 'is_active' => true,
        ]);
    }

    public function test_installation_order_reserves_available_kit_and_uses_confirmed_eta_for_readiness(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'daily_capacity_minutes' => 480, 'max_travel_km' => 100, 'skills' => ['electrical'], 'is_available' => true]);
        $warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Main warehouse', 'is_default' => true, 'is_active' => true]);
        $product = $this->product(false);
        $installation = Installation::create(['service_type' => 'electrical', 'status' => 'approved', 'site_address_line_1' => 'Calle Kit 1']);
        $installation->materialRequirements()->create(['product_id' => $product->id, 'source' => 'quote', 'sku' => $product->sku, 'description' => $product->name, 'planned_quantity' => 10, 'planned_unit_cost' => 500]);
        $template = WorkOrderTemplate::create(['name' => 'Kit planning', 'kind' => 'installation', 'title' => 'Install kit', 'estimated_duration_minutes' => 480, 'required_skills' => ['electrical'], 'checklist' => [['label' => 'Install', 'is_required' => true]], 'is_active' => true]);
        $movement = StockMovement::create(['created_by' => $commercial->id, 'number' => 'MOV-KIT', 'type' => 'receipt', 'posted_at' => now()]);
        $movement->lines()->create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 4, 'unit_cost' => 500]);

        $order = app(WorkOrderFactory::class)->create(['installation_id' => $installation->id, 'work_order_template_id' => $template->id, 'assigned_user_id' => $technician->id, 'scheduled_start' => now()->addDays(10)->setTime(8, 0), 'scheduled_end' => now()->addDays(10)->setTime(16, 0)], $commercial->id);

        $this->assertSame(4.0, (float) StockReservation::where('work_order_id', $order->id)->sum('quantity'));
        $readiness = app(WorkOrderPlanningReadiness::class)->for($order);
        $this->assertFalse($readiness['material_ready']);
        $this->assertSame(6.0, $readiness['shortage_quantity']);
        $supplier = Supplier::create(['legal_name' => 'Kit Supply', 'country' => 'ES', 'payment_terms_days' => 30, 'is_active' => true]);
        $purchase = PurchaseDocument::create(['type' => 'purchase_order', 'installation_id' => $installation->id, 'work_order_id' => $order->id, 'supplier_id' => $supplier->id, 'created_by' => $commercial->id, 'number' => 'PO-KIT', 'status' => 'ordered', 'issue_date' => today(), 'expected_delivery_date' => today()->addDays(5), 'subtotal' => 3000, 'tax_amount' => 630, 'amount' => 3630]);
        $purchase->lines()->create(['product_id' => $product->id, 'position' => 1, 'description' => $product->name, 'quantity' => 6, 'received_quantity' => 0, 'unit_price' => 500, 'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 3000, 'tax_amount' => 630, 'total' => 3630]);

        $readiness = app(WorkOrderPlanningReadiness::class)->for($order->refresh());
        $this->assertTrue($readiness['material_ready']);
        $this->assertFalse($readiness['material_ready_now']);
        $this->assertTrue($readiness['personnel_ready']);
        $this->assertSame(today()->addDays(5)->toDateString(), $readiness['material_ready_on']);
        $this->assertSame('ready', $readiness['status']);
    }
}
