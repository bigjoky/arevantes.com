<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\ReplenishmentPolicy;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReplenishmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_policy_recommends_rounded_quantity_and_creates_a_supplier_rfq(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $warehouse = Warehouse::create(['code' => 'REP', 'name' => 'Replenishment warehouse', 'is_active' => true]);
        $supplier = Supplier::create(['legal_name' => 'Preferred Stock SL', 'country' => 'ES', 'is_active' => true]);
        $product = Product::create([
            'type' => 'product',
            'sku' => 'REP-PANEL',
            'name' => 'Replenishment panel',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 150,
            'cost_price' => 80,
            'track_stock' => true,
            'track_serials' => false,
            'is_active' => true,
        ]);
        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'minimum_order_quantity' => 5,
            'order_multiple' => 5,
            'lead_time_days' => 7,
            'last_unit_price' => 75,
            'currency' => 'EUR',
            'is_preferred' => true,
            'is_active' => true,
        ]);
        $movement = StockMovement::create([
            'created_by' => $user->id,
            'number' => 'MOV-REP-1',
            'type' => 'receipt',
            'posted_at' => now(),
        ]);
        $movement->lines()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_cost' => 70,
        ]);

        $this->actingAs($user)->post(route('replenishment.policies.store'), [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'reorder_point' => 3,
            'target_stock' => 10,
            'is_active' => '1',
        ])->assertRedirect();
        $policy = ReplenishmentPolicy::firstOrFail();

        $this->get(route('replenishment.index'))->assertInertia(fn (Assert $page) => $page
            ->component('purchases/replenishment/index')
            ->where('recommendations.0.on_hand', 2)
            ->where('recommendations.0.projected', 2)
            ->where('recommendations.0.suggested_quantity', 10)
            ->where('recommendations.0.supplier.id', $supplier->id));

        $this->post(route('replenishment.procure'), ['policies' => [$policy->id]])->assertRedirect();
        $request = PurchaseDocument::where('type', 'purchase_rfq')->firstOrFail();
        $this->assertSame($warehouse->id, $request->destination_warehouse_id);
        $this->assertSame($supplier->id, $request->supplier_id);
        $this->assertSame('10.000', $request->lines()->firstOrFail()->quantity);
        $this->assertSame(today()->addDays(7)->toDateString(), $request->expected_delivery_date->toDateString());

        $this->get(route('replenishment.index'))->assertInertia(fn (Assert $page) => $page
            ->where('recommendations.0.inbound', 10)
            ->where('recommendations.0.projected', 12)
            ->where('recommendations.0.needs_action', false));
        $this->post(route('replenishment.procure'), ['policies' => [$policy->id]])->assertSessionHasErrors('policies');
        $this->assertSame(1, PurchaseDocument::where('type', 'purchase_rfq')->count());
    }
}
