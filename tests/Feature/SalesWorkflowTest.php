<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\StockReservation;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_stock_is_reserved_and_consumed_partially_through_the_workflow(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Workflow']);
        $warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Main warehouse', 'is_default' => true]);
        $product = $this->product();
        $order = $this->order($user, $customer, $product, 10);
        $line = $order->lines()->firstOrFail();

        $this->actingAs($user)->post(route('inventory.movements.store'), [
            'type' => 'receipt',
            'warehouse_id' => $warehouse->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 50]],
        ])->assertRedirect();

        $this->get(route('sales-workflow.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('sales/workflow')
            ->where('summary.orders', 1)
            ->where('summary.shortages', 0)
            ->where('orders.0.stock.required', 10)
            ->where('orders.0.stock.reserved', 0));

        $this->post(route('sales-workflow.reserve', $order), ['warehouse_id' => $warehouse->id])->assertRedirect();
        $reservation = StockReservation::firstOrFail();
        $this->assertSame($order->id, $reservation->sales_document_id);
        $this->assertNull($reservation->installation_id);
        $this->assertSame('10.000', $reservation->quantity);

        $this->post(route('sales-documents.convert', $order), [
            'type' => 'delivery_note',
            'quantities' => [$line->id => 4],
        ])->assertRedirect();
        $delivery = SalesDocument::where('type', 'delivery_note')->firstOrFail();
        $this->post(route('sales-documents.dispatch', $delivery), ['warehouse_id' => $warehouse->id])->assertRedirect();

        $this->assertSame('4.000', $reservation->refresh()->consumed_quantity);
        $this->assertSame('active', $reservation->status);
        $this->get(route('sales-workflow.index'))->assertInertia(fn (Assert $page) => $page
            ->where('orders.0.stock.required', 6)
            ->where('orders.0.stock.reserved', 6)
            ->where('summary.dispatches', 0)
            ->where('summary.billing', 1)
            ->where('billing.0.id', $delivery->id));

        $deliveryLine = $delivery->lines()->firstOrFail();
        $this->post(route('sales-documents.convert', $delivery), [
            'type' => 'invoice',
            'quantities' => [$deliveryLine->id => 4],
        ])->assertRedirect();
        $this->get(route('sales-workflow.index'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.billing', 0)
            ->where('orders.0.progress.0.invoiced_quantity', 4));
    }

    public function test_shortages_are_visible_and_cannot_be_reserved(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Shortage']);
        $warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Main warehouse']);
        $order = $this->order($user, $customer, $this->product(), 3);
        $supplier = Supplier::create(['legal_name' => 'Workflow Supplier SL', 'country' => 'ES', 'payment_terms_days' => 30, 'is_active' => true]);
        $alternative = Supplier::create(['legal_name' => 'Alternative Supplier SL', 'country' => 'ES', 'payment_terms_days' => 15, 'is_active' => true]);
        $this->actingAs($user);

        $this->get(route('sales-workflow.index'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.shortages', 1)
            ->where('orders.0.stock.shortage', 3));
        $this->post(route('sales-workflow.reserve', $order), ['warehouse_id' => $warehouse->id])
            ->assertSessionHasErrors('warehouse_id');
        $this->assertDatabaseCount('stock_reservations', 0);

        $this->post(route('sales-workflow.procure', $order), ['supplier_ids' => [$supplier->id, $alternative->id]])->assertRedirect();
        $request = PurchaseDocument::where('type', 'purchase_rfq')->firstOrFail();
        $alternativeRequest = PurchaseDocument::where('type', 'purchase_rfq')->latest('id')->firstOrFail();
        $this->assertSame($order->id, $request->sales_document_id);
        $this->assertSame('3.000', $request->lines()->firstOrFail()->quantity);
        $this->assertSame($request->procurement_batch_id, $alternativeRequest->procurement_batch_id);
        $this->get(route('sales-workflow.index'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.shortages', 0)
            ->where('orders.0.stock.physical_shortage', 3)
            ->where('orders.0.stock.inbound', 3)
            ->where('orders.0.stock.shortage', 0)
            ->has('orders.0.purchases', 2)
            ->where('orders.0.purchases.0.procurement_batch_id', $request->procurement_batch_id));
        $this->post(route('sales-workflow.procure', $order), ['supplier_ids' => [$supplier->id]])->assertSessionHasErrors('supplier_ids');
        $this->assertSame(2, PurchaseDocument::where('type', 'purchase_rfq')->count());

        foreach ([[$request, 55, 8], [$alternativeRequest, 45, 12]] as [$quote, $price, $days]) {
            $quoteLine = $quote->lines()->firstOrFail();
            $this->patch(route('purchase-comparisons.respond', $quote), [
                'supplier_number' => 'OFFER-'.$quote->id,
                'delivery_days' => $days,
                'valid_until' => '2026-08-31',
                'prices' => [$quoteLine->id => $price],
            ])->assertRedirect();
        }
        $this->get(route('purchase-comparisons.show', $request->procurement_batch_id))->assertInertia(fn (Assert $page) => $page
            ->component('purchases/comparison')
            ->has('requests', 2)
            ->where('requests.0.status', 'quoted')
            ->where('requests.1.status', 'quoted'));

        $orderLine = $order->lines()->firstOrFail();
        $this->post(route('sales-documents.convert', $order), [
            'type' => 'delivery_note',
            'quantities' => [$orderLine->id => 3],
        ])->assertRedirect();
        $this->post(route('purchase-comparisons.select', $alternativeRequest))->assertRedirect();
        $purchaseOrder = PurchaseDocument::where('type', 'purchase_order')->firstOrFail();
        $this->assertSame($order->id, $purchaseOrder->sales_document_id);
        $this->assertSame($alternativeRequest->id, $purchaseOrder->source_document_id);
        $this->assertSame('45.00', $purchaseOrder->lines()->firstOrFail()->unit_price);
        $this->assertSame('rejected', $request->refresh()->status);
        $this->assertSame('accepted', $alternativeRequest->refresh()->status);
        $purchaseLine = $purchaseOrder->lines()->firstOrFail();
        $this->post(route('purchases.receive', $purchaseOrder), [
            'warehouse_id' => $warehouse->id,
            'quantities' => [$purchaseLine->id => 3],
        ])->assertRedirect();
        $reservation = StockReservation::firstOrFail();
        $this->assertSame($order->id, $reservation->sales_document_id);
        $this->assertSame('3.000', $reservation->quantity);
        $this->get(route('sales-workflow.index'))->assertInertia(fn (Assert $page) => $page
            ->where('orders.0.stock.reserved', 3)
            ->where('orders.0.stock.physical_shortage', 0)
            ->where('orders.0.stock.inbound', 0));
    }

    private function product(): Product
    {
        return Product::create([
            'type' => 'product',
            'sku' => 'WF-STOCK',
            'name' => 'Workflow stock item',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 100,
            'cost_price' => 50,
            'track_stock' => true,
            'track_serials' => false,
            'is_active' => true,
        ]);
    }

    private function order(User $user, Person $customer, Product $product, float $quantity): SalesDocument
    {
        $order = SalesDocument::create([
            'type' => 'order',
            'person_id' => $customer->id,
            'created_by' => $user->id,
            'number' => 'ORD-WORKFLOW',
            'status' => 'draft',
            'fulfillment_status' => 'pending',
            'billing_status' => 'pending',
            'issue_date' => '2026-07-13',
            'subtotal' => $quantity * 100,
            'discount_amount' => 0,
            'tax_amount' => $quantity * 21,
            'amount' => $quantity * 121,
            'customer_snapshot' => ['first_name' => $customer->first_name],
        ]);
        $order->lines()->create([
            'product_id' => $product->id,
            'position' => 1,
            'description' => $product->name,
            'quantity' => $quantity,
            'unit_price' => 100,
            'discount_percent' => 0,
            'tax_percent' => 21,
            'subtotal' => $quantity * 100,
            'discount_amount' => 0,
            'tax_amount' => $quantity * 21,
            'total' => $quantity * 121,
        ]);

        return $order;
    }
}
