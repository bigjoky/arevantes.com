<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\Inventory\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplyMobileTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $product = Product::create(['name' => 'Material sintético 04', 'sku' => 'QA-04', 'type' => 'product', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'track_stock' => true, 'track_serials' => false, 'is_active' => true, 'cost_price' => 10, 'sale_price' => 15]);
        $warehouse = Warehouse::create(['code' => 'QA-04', 'name' => 'Principal sintético', 'is_active' => true, 'is_usable' => true]);
        $quarantine = Warehouse::create(['code' => 'QA-Q', 'name' => 'Cuarentena sintética', 'is_active' => true, 'is_usable' => false]);
        $installation = Installation::create(['project_reference' => 'QA-PRO-04', 'service_type' => 'electrical', 'status' => 'approved', 'site_address_line_1' => 'Calle Sintética 1']);
        app(StockLedger::class)->post(['type' => 'receipt', 'warehouse_id' => $warehouse->id, 'lines' => [['product_id' => $product->id, 'quantity' => 10]]], $user->id);
        app(StockLedger::class)->post(['type' => 'receipt', 'warehouse_id' => $quarantine->id, 'lines' => [['product_id' => $product->id, 'quantity' => 5]]], $user->id);

        return [$user, $product, $warehouse, $quarantine, $installation];
    }

    public function test_mobile_queries_preserve_warehouse_positions_and_never_write_stock(): void
    {
        [$user, $product, $warehouse, $quarantine] = $this->fixtures();
        $before = [StockMovement::count(), StockMovementLine::count(), StockReservation::count()];
        $this->actingAs($user)->get('/inventory?view=stock&warehouse='.$quarantine->id.'&search=QA-04')->assertOk()->assertInertia(fn (Assert $page) => $page->has('positions.data', 1)->where('positions.data.0.warehouse.id', $quarantine->id)->where('positions.data.0.on_hand', 5)->where('positions.data.0.available', 0));
        $this->get('/inventory?view=stock&warehouse='.$warehouse->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('positions.data.0.available', 10));
        $this->get('/catalog?search=does-not-exist')->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
        $this->get('/catalog/'.$product->id)->assertOk()->assertInertia(fn (Assert $page) => $page->has('positions', 2));
        $this->assertSame($before, [StockMovement::count(), StockMovementLine::count(), StockReservation::count()]);
    }

    public function test_reservation_limits_use_existing_backend_and_only_usable_warehouses(): void
    {
        [$user, $product, $warehouse, $quarantine, $installation] = $this->fixtures();
        $this->actingAs($user);
        $payload = ['installation_id' => $installation->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 11];
        $this->postJson('/inventory/reservations', $payload)->assertUnprocessable();
        $this->postJson('/inventory/reservations', [...$payload, 'quantity' => -1])->assertUnprocessable();
        $this->postJson('/inventory/reservations', [...$payload, 'warehouse_id' => $quarantine->id, 'quantity' => 1])->assertUnprocessable();
        $this->assertSame(0, StockReservation::count());
        $this->assertSame(2, StockMovement::count());
        $this->post('/inventory/reservations', [...$payload, 'quantity' => 3])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, StockReservation::count());
        $this->assertSame(2, StockMovement::count());
    }

    public function test_order_consumption_preserves_assignment_and_operation_idempotency(): void
    {
        [$user, $product, $warehouse, $quarantine, $installation] = $this->fixtures();
        $tech = User::factory()->create(['role' => 'technician']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'number' => 'QA-OT-04', 'title' => 'Consumo sintético', 'kind' => 'installation', 'status' => 'planned', 'assigned_user_id' => $tech->id]);
        $reservation = StockReservation::create(['installation_id' => $installation->id, 'work_order_id' => $order->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 3, 'consumed_quantity' => 0, 'status' => 'active']);
        $this->actingAs($user)->get('/work-orders/'.$order->id.'?tab=materials')->assertOk()->assertInertia(fn (Assert $page) => $page->has('materialOptions.products', 1)->has('materialOptions.warehouses', 1));
        $this->actingAs($tech)->get('/work-orders/'.$order->id.'?tab=materials')->assertOk()->assertInertia(fn (Assert $page) => $page->where('materialOptions', null)->has('content.data', 1));
        $input = ['operation_key' => (string) Str::uuid(), 'quantity' => 2];
        $url = '/work-orders/'.$order->id.'/materials/'.$reservation->id.'/consume';
        $this->post($url, $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, StockMovement::where('type', 'issue')->count());
        $this->assertSame(2.0, (float) $reservation->fresh()->consumed_quantity);
        $this->postJson($url, [...$input, 'operation_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(2.0, (float) $reservation->fresh()->consumed_quantity);
        $other = User::factory()->create(['role' => 'technician']);
        $this->actingAs($other)->get('/work-orders/'.$order->id.'?tab=materials')->assertForbidden();
        $this->postJson($url, [...$input, 'quantity' => 1])->assertForbidden();
        $this->get('/inventory')->assertForbidden();
    }
}
