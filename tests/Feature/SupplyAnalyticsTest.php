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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplyAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_combines_net_spend_uninvoiced_commitments_and_price_changes(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Analytics Supply SL', 'country' => 'ES', 'is_active' => true]);
        $product = Product::create([
            'type' => 'product',
            'sku' => 'ANALYTICS-PANEL',
            'name' => 'Analytics panel',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 150,
            'cost_price' => 90,
            'track_stock' => true,
            'track_serials' => false,
            'is_active' => true,
        ]);
        $this->actingAs($user)->post(route('purchases.store'), [
            'type' => 'purchase_order',
            'supplier_id' => $supplier->id,
            'issue_date' => today()->toDateString(),
            'shipping_cost' => 100,
            'lines' => [[
                'product_id' => $product->id,
                'description' => 'Analytics panel',
                'quantity' => 10,
                'unit_price' => 100,
                'discount_percent' => 10,
                'tax_percent' => 21,
            ]],
        ])->assertRedirect();
        $order = PurchaseDocument::where('type', 'purchase_order')->firstOrFail();
        $orderLine = $order->lines()->firstOrFail();

        $invoice = PurchaseDocument::create([
            'type' => 'supplier_invoice',
            'source_document_id' => $order->id,
            'supplier_id' => $supplier->id,
            'document_series_id' => $order->document_series_id,
            'created_by' => $user->id,
            'number' => 'PINV-ANALYTICS-1',
            'supplier_number' => 'SUP-INV-1',
            'status' => 'registered',
            'matching_status' => 'matched',
            'issue_date' => today(),
            'subtotal' => 400,
            'tax_amount' => 84,
            'shipping_cost' => 40,
            'amount' => 484,
            'paid_amount' => 0,
        ]);
        $invoice->lines()->create([
            'source_line_id' => $orderLine->id,
            'product_id' => $product->id,
            'position' => 1,
            'description' => 'Analytics panel',
            'quantity' => 4,
            'unit_price' => 100,
            'discount_percent' => 10,
            'tax_percent' => 21,
            'subtotal' => 360,
            'tax_amount' => 75.60,
            'total' => 435.60,
        ]);
        PurchaseDocument::create([
            'type' => 'supplier_credit_note',
            'source_document_id' => $invoice->id,
            'supplier_id' => $supplier->id,
            'document_series_id' => $order->document_series_id,
            'created_by' => $user->id,
            'number' => 'PCN-ANALYTICS-1',
            'supplier_number' => 'SUP-CN-1',
            'status' => 'registered',
            'matching_status' => 'matched',
            'issue_date' => today(),
            'subtotal' => -82.64,
            'tax_amount' => -17.36,
            'shipping_cost' => 0,
            'amount' => -100,
            'paid_amount' => 0,
        ]);

        $supplierProduct = SupplierProduct::where('supplier_id', $supplier->id)->where('product_id', $product->id)->firstOrFail();
        SupplierProductPrice::where('supplier_product_id', $supplierProduct->id)->update(['recorded_at' => now()->subDays(2)]);
        SupplierProductPrice::create([
            'supplier_product_id' => $supplierProduct->id,
            'unit_price' => 90,
            'currency' => 'EUR',
            'recorded_at' => now()->subDay(),
        ]);
        SupplierProductPrice::create([
            'supplier_product_id' => $supplierProduct->id,
            'unit_price' => 110,
            'currency' => 'EUR',
            'recorded_at' => now(),
        ]);

        $this->get(route('supply-analytics', ['months' => 3]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('purchases/analytics/index')
                ->where('months', 3)
                ->where('metrics.period_spend', 384)
                ->where('metrics.open_commitments', 726)
                ->where('metrics.total_exposure', 1110)
                ->where('metrics.supplier_concentration', 100)
                ->where('metrics.open_orders', 1)
                ->where('monthly_spend.2.amount', 384)
                ->where('commitments.0.amount', 726)
                ->where('price_alerts.0.previous_price', 90)
                ->where('price_alerts.0.current_price', 110)
                ->where('price_alerts.0.change_percent', 22.2));
    }
}
