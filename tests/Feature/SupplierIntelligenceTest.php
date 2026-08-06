<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierProductPrice;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_lines_automatically_build_supplier_catalog_and_price_history(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        [$supplier, $product] = $this->catalog();

        $this->actingAs($user)->post(route('purchases.store'), [
            'type' => 'purchase_order',
            'supplier_id' => $supplier->id,
            'issue_date' => '2026-07-25',
            'expected_delivery_date' => '2026-08-01',
            'lines' => [[
                'product_id' => $product->id,
                'description' => 'High-efficiency panel',
                'quantity' => 4,
                'unit_price' => 87.50,
                'discount_percent' => 0,
                'tax_percent' => 21,
            ]],
        ])->assertRedirect();

        $supplierProduct = SupplierProduct::firstOrFail();
        $this->assertSame($supplier->id, $supplierProduct->supplier_id);
        $this->assertSame($product->id, $supplierProduct->product_id);
        $this->assertSame('87.50', $supplierProduct->last_unit_price);
        $this->assertSame(1, SupplierProductPrice::count());
        $this->assertDatabaseHas('supplier_product_prices', ['unit_price' => 87.50]);
    }

    public function test_supplier_scorecard_tracks_delivery_and_preferred_catalog_source(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        [$supplier, $product] = $this->catalog();
        $alternative = Supplier::create(['legal_name' => 'Alternative Supply SL', 'country' => 'ES', 'is_active' => true]);
        $warehouse = Warehouse::create(['code' => 'INTEL', 'name' => 'Intelligence warehouse']);
        $this->actingAs($user);

        $this->post(route('purchases.store'), [
            'type' => 'purchase_order',
            'supplier_id' => $supplier->id,
            'issue_date' => '2026-07-25',
            'expected_delivery_date' => now()->addDay()->toDateString(),
            'lines' => [[
                'product_id' => $product->id,
                'description' => 'Panel',
                'quantity' => 2,
                'unit_price' => 90,
                'discount_percent' => 0,
                'tax_percent' => 21,
            ]],
        ])->assertRedirect();
        $order = PurchaseDocument::firstOrFail();
        $line = $order->lines()->firstOrFail();
        $this->post(route('purchases.receive', $order), [
            'warehouse_id' => $warehouse->id,
            'quantities' => [$line->id => 2],
        ])->assertRedirect();

        $this->post(route('supplier-intelligence.products.store', $alternative), [
            'product_id' => $product->id,
            'minimum_order_quantity' => 5,
            'order_multiple' => 5,
            'lead_time_days' => 3,
            'currency' => 'EUR',
            'is_preferred' => '1',
            'is_active' => '1',
        ])->assertRedirect();
        $preferred = SupplierProduct::where('supplier_id', $alternative->id)->firstOrFail();
        $this->assertTrue($preferred->is_preferred);

        $original = SupplierProduct::where('supplier_id', $supplier->id)->firstOrFail();
        $this->patch(route('supplier-intelligence.products.update', $original), [
            'product_id' => $product->id,
            'minimum_order_quantity' => 1,
            'order_multiple' => 1,
            'currency' => 'EUR',
            'is_preferred' => '1',
            'is_active' => '1',
        ])->assertRedirect();
        $this->assertTrue($original->refresh()->is_preferred);
        $this->assertFalse($preferred->refresh()->is_preferred);

        $this->get(route('supplier-intelligence.index'))->assertInertia(fn (Assert $page) => $page
            ->component('purchases/suppliers/index')
            ->has('suppliers', 2)
            ->where('suppliers.1.delivery_score', 100));
        $this->get(route('supplier-intelligence.show', $supplier))->assertInertia(fn (Assert $page) => $page
            ->component('purchases/suppliers/show')
            ->where('scorecard.delivery_score', 100)
            ->has('supplier.products', 1));
    }

    private function catalog(): array
    {
        $supplier = Supplier::create(['legal_name' => 'Intelligent Supply SL', 'country' => 'ES', 'is_active' => true]);
        $product = Product::create([
            'type' => 'product',
            'sku' => 'INTEL-PANEL',
            'name' => 'High-efficiency panel',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 150,
            'cost_price' => 90,
            'track_stock' => true,
            'track_serials' => false,
            'is_active' => true,
        ]);

        return [$supplier, $product];
    }
}
