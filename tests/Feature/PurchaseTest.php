<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\SerialNumber;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_purchase_order_includes_transport_cost_in_its_total(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Transport Supply SL', 'country' => 'ES']);
        $product = Product::create(['type' => 'product', 'sku' => 'SHIP-10', 'name' => 'Panel', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 150, 'cost_price' => 100, 'is_active' => true]);

        $this->actingAs($user)->post(route('purchases.store'), [
            'type' => 'purchase_order',
            'supplier_id' => $supplier->id,
            'issue_date' => '2026-07-13',
            'shipping_cost' => 25.50,
            'lines' => [[
                'product_id' => $product->id,
                'description' => 'Panel',
                'quantity' => 2,
                'unit_price' => 100,
                'discount_percent' => 0,
                'tax_percent' => 21,
            ]],
        ])->assertRedirect();

        $order = PurchaseDocument::firstOrFail();

        $this->assertSame('25.50', $order->shipping_cost);
        $this->assertSame('267.50', $order->amount);
    }

    public function test_transport_cost_can_be_added_to_an_existing_purchase_order(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Legacy Supply SL', 'country' => 'ES']);
        $product = Product::create(['type' => 'product', 'sku' => 'OLD-10', 'name' => 'Inverter', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 150, 'cost_price' => 100, 'is_active' => true]);

        $this->actingAs($user)->post(route('purchases.store'), [
            'type' => 'purchase_order',
            'supplier_id' => $supplier->id,
            'issue_date' => '2026-07-01',
            'lines' => [[
                'product_id' => $product->id,
                'description' => 'Inverter',
                'quantity' => 1,
                'unit_price' => 100,
                'discount_percent' => 0,
                'tax_percent' => 21,
            ]],
        ])->assertRedirect();

        $order = PurchaseDocument::firstOrFail();
        $this->assertSame('0.00', $order->shipping_cost);
        $this->assertSame('121.00', $order->amount);

        $this->patch(route('purchases.shipping.update', $order), [
            'shipping_cost' => 18.75,
        ])->assertRedirect();

        $this->assertSame('18.75', $order->refresh()->shipping_cost);
        $this->assertSame('139.75', $order->amount);
    }

    public function test_a_purchase_order_receives_serialized_stock_and_creates_a_supplier_invoice(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Energy Supply SL', 'country' => 'ES', 'payment_terms_days' => 30]);
        $warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Main warehouse']);
        $product = Product::create(['type' => 'product', 'sku' => 'INV-10', 'name' => 'Inverter', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 1200, 'cost_price' => 800, 'track_stock' => true, 'track_serials' => true, 'is_active' => true]);
        $this->actingAs($user);

        $this->post(route('purchases.store'), [
            'type' => 'purchase_order', 'supplier_id' => $supplier->id, 'issue_date' => '2026-07-12',
            'lines' => [['product_id' => $product->id, 'description' => 'Inverter', 'quantity' => 2, 'unit_price' => 800, 'discount_percent' => 0, 'tax_percent' => 21]],
        ])->assertRedirect();
        $order = PurchaseDocument::firstOrFail();
        $this->assertSame('PC-2026-00001', $order->number);
        $this->assertSame('1936.00', $order->amount);

        $line = $order->lines()->firstOrFail();
        $this->post(route('purchases.receive', $order), [
            'warehouse_id' => $warehouse->id, 'supplier_delivery_note' => 'SUP-ALB-1',
            'quantities' => [$line->id => 2], 'serials' => [$line->id => "SER-001\nSER-002"],
        ])->assertRedirect();
        $this->assertSame('received', $order->refresh()->status);
        $this->assertSame('2.000', $line->refresh()->received_quantity);
        $this->assertDatabaseHas('goods_receipts', ['purchase_document_id' => $order->id, 'number' => 'EM-2026-00001']);
        $this->assertSame($warehouse->id, SerialNumber::where('serial_number', 'SER-001')->value('warehouse_id'));

        $this->post(route('purchases.invoice', $order), ['supplier_number' => 'FS-2026-88', 'issue_date' => '2026-07-12', 'due_date' => '2026-08-11'])->assertRedirect();
        $invoice = PurchaseDocument::where('type', 'supplier_invoice')->firstOrFail();
        $this->assertSame('FR-2026-00001', $invoice->number);
        $this->assertSame('FS-2026-88', $invoice->supplier_number);
        $this->assertSame('matched', $invoice->matching_status);
        $this->assertSame('0.00', $invoice->match_variance_amount);
        $this->assertSame('AST-2026-000001', $invoice->accountingEntry()->firstOrFail()->number);
        $this->post(route('purchases.invoice', $order), ['supplier_number' => 'DUPLICATE', 'issue_date' => '2026-07-12'])->assertSessionHasErrors('lines');
        $this->assertSame(1, PurchaseDocument::where('type', 'supplier_invoice')->count());
    }

    public function test_an_invoice_variance_requires_approval_before_accounting_or_payment(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Variance Supply SL', 'country' => 'ES', 'payment_terms_days' => 30]);
        $warehouse = Warehouse::create(['code' => 'VAR', 'name' => 'Variance warehouse']);
        $product = Product::create(['type' => 'product', 'sku' => 'VAR-10', 'name' => 'Battery', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 1200, 'cost_price' => 800, 'track_stock' => true, 'track_serials' => false, 'is_active' => true]);
        $this->actingAs($user);

        $this->post(route('purchases.store'), [
            'type' => 'purchase_order', 'supplier_id' => $supplier->id, 'issue_date' => '2026-07-12',
            'lines' => [['product_id' => $product->id, 'description' => 'Battery', 'quantity' => 2, 'unit_price' => 800, 'discount_percent' => 0, 'tax_percent' => 21]],
        ])->assertRedirect();
        $order = PurchaseDocument::where('type', 'purchase_order')->firstOrFail();
        $line = $order->lines()->firstOrFail();
        $this->post(route('purchases.receive', $order), [
            'warehouse_id' => $warehouse->id,
            'quantities' => [$line->id => 1],
        ])->assertRedirect();

        $this->post(route('purchases.invoice', $order), [
            'supplier_number' => 'VAR-2026-1',
            'issue_date' => '2026-07-13',
            'lines' => [['line_id' => $line->id, 'quantity' => 2, 'unit_price' => 850]],
        ])->assertRedirect();

        $invoice = PurchaseDocument::where('type', 'supplier_invoice')->firstOrFail();
        $this->assertSame('review_required', $invoice->status);
        $this->assertSame('review_required', $invoice->matching_status);
        $this->assertSame('1089.00', $invoice->match_variance_amount);
        $this->assertNull($invoice->accountingEntry()->first());
        $this->post(route('supplier-invoices.payments.store', $invoice), [
            'paid_at' => '2026-07-13', 'amount' => 100, 'method' => 'bank_transfer',
        ])->assertStatus(422);

        $this->post(route('supplier-invoices.approve', $invoice), [
            'approval_notes' => 'Supplier confirmed an urgent price surcharge and the second unit is in transit.',
        ])->assertRedirect();
        $invoice->refresh();
        $this->assertSame('registered', $invoice->status);
        $this->assertSame('approved', $invoice->matching_status);
        $this->assertSame($user->id, $invoice->approved_by);
        $this->assertNotNull($invoice->approved_at);
        $this->assertNotNull($invoice->accountingEntry()->first());
    }

    public function test_an_order_supports_partial_invoices_supplier_credits_and_goods_returns(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Partial Supply SL', 'country' => 'ES', 'payment_terms_days' => 30]);
        $warehouse = Warehouse::create(['code' => 'PART', 'name' => 'Partial warehouse']);
        $product = Product::create(['type' => 'product', 'sku' => 'PART-10', 'name' => 'Solar panel', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 1200, 'cost_price' => 800, 'track_stock' => true, 'track_serials' => false, 'is_active' => true]);
        $this->actingAs($user);

        $this->post(route('purchases.store'), [
            'type' => 'purchase_order', 'supplier_id' => $supplier->id, 'issue_date' => '2026-07-12',
            'lines' => [['product_id' => $product->id, 'description' => 'Solar panel', 'quantity' => 4, 'unit_price' => 800, 'discount_percent' => 0, 'tax_percent' => 21]],
        ])->assertRedirect();
        $order = PurchaseDocument::where('type', 'purchase_order')->firstOrFail();
        $line = $order->lines()->firstOrFail();
        $this->post(route('purchases.receive', $order), ['warehouse_id' => $warehouse->id, 'quantities' => [$line->id => 4]])->assertRedirect();

        $this->post(route('purchases.invoice', $order), [
            'supplier_number' => 'PART-1', 'issue_date' => '2026-07-12',
            'lines' => [['line_id' => $line->id, 'quantity' => 2, 'unit_price' => 800]],
        ])->assertRedirect();
        $firstInvoice = PurchaseDocument::where('type', 'supplier_invoice')->firstOrFail();
        $this->assertSame('matched', $firstInvoice->matching_status);
        $this->assertSame('partially_invoiced', $order->refresh()->invoicing_status);

        $this->post(route('purchases.invoice', $order), [
            'supplier_number' => 'PART-1', 'issue_date' => '2026-07-13',
            'lines' => [['line_id' => $line->id, 'quantity' => 2, 'unit_price' => 800]],
        ])->assertSessionHasErrors('supplier_number');

        $this->post(route('purchases.invoice', $order), [
            'supplier_number' => 'PART-2', 'issue_date' => '2026-07-13',
            'lines' => [['line_id' => $line->id, 'quantity' => 2, 'unit_price' => 800]],
        ])->assertRedirect();
        $this->assertSame(2, PurchaseDocument::where('type', 'supplier_invoice')->count());
        $this->assertSame('invoiced', $order->refresh()->invoicing_status);

        $invoiceLine = $firstInvoice->lines()->firstOrFail();
        $this->post(route('supplier-invoices.credit-note', $firstInvoice), [
            'supplier_number' => 'AB-1', 'issue_date' => '2026-07-14',
            'lines' => [['line_id' => $invoiceLine->id, 'quantity' => 1]],
        ])->assertRedirect();
        $credit = PurchaseDocument::where('type', 'supplier_credit_note')->firstOrFail();
        $this->assertSame('-968.00', $credit->amount);
        $this->assertNotNull($credit->accountingEntry()->first());
        $this->assertSame('partially_invoiced', $order->refresh()->invoicing_status);
        $this->post(route('supplier-invoices.payments.store', $firstInvoice), ['paid_at' => '2026-07-14', 'amount' => 969, 'method' => 'bank_transfer'])->assertSessionHasErrors('amount');
        $this->post(route('supplier-invoices.payments.store', $firstInvoice), ['paid_at' => '2026-07-14', 'amount' => 968, 'method' => 'bank_transfer'])->assertRedirect();
        $this->assertSame('paid', $firstInvoice->refresh()->status);

        $this->post(route('purchases.return', $order), [
            'warehouse_id' => $warehouse->id, 'supplier_return_number' => 'DEV-SUP-1', 'quantities' => [$line->id => 1],
        ])->assertRedirect();
        $this->assertDatabaseHas('goods_returns', ['purchase_document_id' => $order->id, 'supplier_return_number' => 'DEV-SUP-1']);
        $this->assertSame('1.000', $line->refresh()->returned_quantity);
        $this->assertSame('partially_invoiced', $order->refresh()->invoicing_status);
        $this->assertDatabaseHas('stock_movements', ['purchase_document_id' => $order->id, 'type' => 'purchase_return']);
    }
}
