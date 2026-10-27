<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Installation;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\SerialNumber;
use App\Models\StockReservation;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoodsReceiptWarehouseCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $supplier = Supplier::create(['legal_name' => 'Proveedor de prueba', 'country' => 'ES']);
        $product = Product::create(['type' => 'product', 'sku' => 'CORRECTION', 'name' => 'Producto ficticio', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 100, 'cost_price' => 80, 'track_stock' => true, 'track_serials' => true, 'is_active' => true]);
        $from = Warehouse::create(['code' => 'RCPT-FROM', 'name' => 'Origen de prueba', 'is_active' => true, 'is_usable' => true]);
        $to = Warehouse::create(['code' => 'RCPT-TO', 'name' => 'Destino de prueba', 'is_active' => true, 'is_usable' => true]);
        $this->post(route('purchases.store'), ['type' => 'purchase_order', 'supplier_id' => $supplier->id, 'issue_date' => today()->toDateString(), 'lines' => [['product_id' => $product->id, 'description' => $product->name, 'quantity' => 2, 'unit_price' => 80, 'discount_percent' => 0, 'tax_percent' => 21]]])->assertRedirect()->assertSessionHasNoErrors();
        $order = PurchaseDocument::where('type', 'purchase_order')->firstOrFail();
        $line = $order->lines()->firstOrFail();
        $this->post(route('purchases.receive', $order), ['warehouse_id' => $from->id, 'quantities' => [$line->id => 1], 'serials' => [$line->id => 'LOCAL-CORRECTION-1']])->assertRedirect()->assertSessionHasNoErrors();

        return [$order, $order->receipts()->firstOrFail(), $from, $to, $product];
    }

    public function test_partial_receipt_warehouse_correction_preserves_quantities_serials_and_audit_and_is_idempotent(): void
    {
        [$order, $receipt, $from, $to] = $this->fixture();
        $data = ['warehouse_id' => $to->id, 'expected_warehouse_id' => $from->id, 'reason' => 'Almacén seleccionado por error', 'operation_key' => (string) Str::uuid()];
        $this->post(route('purchase-receipts.warehouse', [$order, $receipt]), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($to->id, $receipt->fresh()->warehouse_id);
        $this->assertSame('partially_received', $order->fresh()->status);
        $this->assertEquals(1, $order->lines()->firstOrFail()->received_quantity);
        $this->assertSame($to->id, SerialNumber::where('serial_number', 'LOCAL-CORRECTION-1')->value('warehouse_id'));
        $this->assertDatabaseCount('stock_movements', 2);
        $this->post(route('purchase-receipts.warehouse', [$order, $receipt]), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertSame(1, AuditLog::where('event', 'receipt_warehouse_corrected')->count());
        $this->actingAs(User::factory()->create(['role' => 'technician']))->post(route('purchase-receipts.warehouse', [$order, $receipt]), $data)->assertForbidden();
    }

    public function test_correction_is_rejected_when_receipt_material_was_already_issued(): void
    {
        [$order, $receipt, $from, $to, $product] = $this->fixture();
        app(StockLedger::class)->post(['type' => 'issue', 'warehouse_id' => $from->id, 'lines' => [['product_id' => $product->id, 'quantity' => 1, 'serials' => 'LOCAL-CORRECTION-1']]], auth()->id());
        $this->post(route('purchase-receipts.warehouse', [$order, $receipt]), ['warehouse_id' => $to->id, 'expected_warehouse_id' => $from->id, 'reason' => 'Corrección no permitida', 'operation_key' => (string) Str::uuid()])->assertSessionHasErrors('warehouse_id');
        $this->assertSame($from->id, $receipt->fresh()->warehouse_id);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_reserved_receipt_cannot_be_relocated_without_resolving_the_reservation(): void
    {
        [$order, $receipt, $from, $to, $product] = $this->fixture();
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Prueba']);
        StockReservation::create(['installation_id' => $installation->id, 'warehouse_id' => $from->id, 'product_id' => $product->id, 'quantity' => 1, 'consumed_quantity' => 0, 'status' => 'active']);
        $this->post(route('purchase-receipts.warehouse', [$order, $receipt]), ['warehouse_id' => $to->id, 'expected_warehouse_id' => $from->id, 'reason' => 'No mover material reservado', 'operation_key' => (string) Str::uuid()])->assertSessionHasErrors();
        $this->assertSame($from->id, $receipt->fresh()->warehouse_id);
        $this->assertDatabaseCount('stock_movements', 1);
    }
}
