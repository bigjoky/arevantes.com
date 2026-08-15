<?php

namespace Tests\Feature;

use App\Models\PermissionProfile;
use App\Models\Product;
use App\Models\PurchaseApprovalRule;
use App\Models\PurchaseDocument;
use App\Models\SerialNumber;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
                'tax_percent' => 10,
            ]],
        ])->assertRedirect();

        $order = PurchaseDocument::firstOrFail();

        $this->assertSame('25.50', $order->shipping_cost);
        $this->assertSame('225.50', $order->subtotal);
        $this->assertSame('25.36', $order->tax_amount);
        $this->assertSame('250.86', $order->amount);
    }

    public function test_a_high_value_order_requires_independent_approval_before_execution(): void
    {
        Mail::fake();
        $creator = User::factory()->create(['role' => 'commercial']);
        $approver = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Strategic Supply SL', 'country' => 'ES']);
        $warehouse = Warehouse::create(['code' => 'APPROVAL', 'name' => 'Approval warehouse']);
        $product = Product::create([
            'type' => 'product',
            'sku' => 'HIGH-VALUE-10',
            'name' => 'High-value inverter',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 15000,
            'cost_price' => 10000,
            'track_stock' => true,
            'is_active' => true,
        ]);

        $this->actingAs($creator)->post(route('purchases.store'), [
            'type' => 'purchase_order',
            'supplier_id' => $supplier->id,
            'issue_date' => '2026-08-11',
            'lines' => [[
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => 1,
                'unit_price' => 10000,
                'discount_percent' => 0,
                'tax_percent' => 21,
            ]],
        ])->assertRedirect();

        $order = PurchaseDocument::where('type', 'purchase_order')->firstOrFail();
        $this->assertSame('pending_approval', $order->status);
        $this->assertNull($order->locked_at);
        $this->assertDatabaseHas('purchase_document_events', [
            'purchase_document_id' => $order->id,
            'event' => 'approval_requested',
        ]);
        $this->assertDatabaseHas('purchase_order_approvals', [
            'purchase_document_id' => $order->id,
            'status' => 'pending',
            'reason' => 'Default order amount threshold',
        ]);

        $this->post(route('purchases.receive', $order), [
            'warehouse_id' => $warehouse->id,
        ])->assertUnprocessable();
        $this->post(route('purchases.send', $order), [
            'recipient_email' => 'orders@strategic-supply.test',
        ])->assertUnprocessable();
        Mail::assertNothingSent();
        $this->post(route('purchases.approve', $order), [
            'approval_notes' => 'Creator cannot release their own order.',
        ])->assertForbidden();

        $this->actingAs($approver)->post(route('purchases.approve', $order), [
            'approval_notes' => 'Amount and supplier conditions independently reviewed.',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('ordered', $order->status);
        $this->assertSame($approver->id, $order->approved_by);
        $this->assertNotNull($order->approved_at);
        $this->assertNotNull($order->locked_at);
        $this->assertDatabaseHas('purchase_document_events', [
            'purchase_document_id' => $order->id,
            'event' => 'purchase_order_approved',
        ]);
    }

    public function test_matching_approval_rules_are_sequential_and_visible_in_the_action_center(): void
    {
        $creator = User::factory()->create(['role' => 'commercial']);
        $approver = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Matrix Supply SL', 'country' => 'ES']);
        $product = Product::create([
            'type' => 'product',
            'sku' => 'MATRIX-10',
            'name' => 'Matrix panel',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 200,
            'cost_price' => 100,
            'track_stock' => true,
            'is_active' => true,
        ]);
        PurchaseApprovalRule::create([
            'name' => 'Product control',
            'product_type' => 'product',
            'min_amount' => 100,
            'priority' => 10,
            'is_active' => true,
            'created_by' => $approver->id,
        ]);
        PurchaseApprovalRule::create([
            'name' => 'Strategic supplier control',
            'supplier_id' => $supplier->id,
            'min_amount' => 200,
            'priority' => 20,
            'is_active' => true,
            'created_by' => $approver->id,
        ]);

        $this->actingAs($creator)->post(route('purchases.store'), [
            'type' => 'purchase_order',
            'supplier_id' => $supplier->id,
            'issue_date' => today()->toDateString(),
            'lines' => [[
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => 2,
                'unit_price' => 100,
                'discount_percent' => 0,
                'tax_percent' => 21,
            ]],
        ])->assertRedirect();

        $order = PurchaseDocument::where('type', 'purchase_order')->firstOrFail();
        $this->assertSame('pending_approval', $order->status);
        $this->assertSame(2, $order->orderApprovals()->where('status', 'pending')->count());

        $this->actingAs($approver)->getJson(route('action-center'))
            ->assertOk()
            ->assertJsonPath('groups.0.label', 'Purchase approvals')
            ->assertJsonPath('groups.0.items.0.id', 'purchase-approval-'.$order->id);

        $this->post(route('purchases.approve', $order), [
            'approval_notes' => 'Product scope checked.',
        ])->assertRedirect();
        $this->assertSame('pending_approval', $order->refresh()->status);
        $this->assertSame(1, $order->orderApprovals()->where('status', 'pending')->count());

        $this->post(route('purchases.approve', $order), [
            'approval_notes' => 'Supplier scope checked.',
        ])->assertRedirect();
        $this->assertSame('ordered', $order->refresh()->status);
        $this->assertSame(2, $order->orderApprovals()->where('status', 'approved')->count());
    }

    public function test_an_approval_requirement_can_reject_an_order(): void
    {
        $creator = User::factory()->create(['role' => 'commercial']);
        $approver = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Rejected Supply SL', 'country' => 'ES']);
        $product = Product::create(['type' => 'service', 'sku' => 'REJECT-10', 'name' => 'Commissioning', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 500, 'cost_price' => 300, 'is_active' => true]);
        PurchaseApprovalRule::create(['name' => 'Service review', 'product_type' => 'service', 'min_amount' => 100, 'priority' => 10, 'is_active' => true, 'created_by' => $approver->id]);
        PurchaseApprovalRule::create(['name' => 'Supplier review', 'supplier_id' => $supplier->id, 'min_amount' => 100, 'priority' => 20, 'is_active' => true, 'created_by' => $approver->id]);

        $this->actingAs($creator)->post(route('purchases.store'), [
            'type' => 'purchase_order', 'supplier_id' => $supplier->id, 'issue_date' => today()->toDateString(),
            'lines' => [['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price' => 300, 'discount_percent' => 0, 'tax_percent' => 21]],
        ])->assertRedirect();
        $order = PurchaseDocument::where('type', 'purchase_order')->firstOrFail();

        $this->actingAs($approver)->post(route('purchases.approve', $order), [
            'decision' => 'rejected',
            'approval_notes' => 'Commercial terms need renegotiation.',
        ])->assertRedirect();

        $this->assertSame('approval_rejected', $order->refresh()->status);
        $this->assertDatabaseHas('purchase_order_approvals', ['purchase_document_id' => $order->id, 'status' => 'rejected', 'decided_by' => $approver->id]);
        $this->assertDatabaseHas('purchase_order_approvals', ['purchase_document_id' => $order->id, 'status' => 'cancelled', 'decided_by' => $approver->id]);
        $this->assertSame(0, $order->orderApprovals()->where('status', 'pending')->count());
        $this->post(route('purchases.receive', $order), [])->assertUnprocessable();
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
            'shipping_cost' => 50,
            'lines' => [['product_id' => $product->id, 'description' => 'Inverter', 'quantity' => 2, 'unit_price' => 800, 'discount_percent' => 0, 'tax_percent' => 21]],
        ])->assertRedirect();
        $order = PurchaseDocument::firstOrFail();
        $this->assertSame('PC-2026-00001', $order->number);
        $this->assertSame('1996.50', $order->amount);

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
        $this->assertSame('50.00', $invoice->shipping_cost);
        $this->assertSame('1650.00', $invoice->subtotal);
        $this->assertSame('346.50', $invoice->tax_amount);
        $this->assertSame('1996.50', $invoice->amount);
        $this->assertSame('AST-2026-000001', $invoice->accountingEntry()->firstOrFail()->number);
        $this->assertEqualsWithDelta(
            $invoice->accountingEntry->lines->sum('debit'),
            $invoice->accountingEntry->lines->sum('credit'),
            0.001,
        );
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
        $this->actingAs($this->financeUser())->post(route('supplier-invoices.payments.store', $invoice), [
            'paid_at' => '2026-07-13', 'amount' => 100, 'method' => 'bank_transfer',
        ])->assertStatus(422);

        $this->actingAs($user);
        $this->post(route('supplier-invoices.approve', $invoice), [
            'approval_notes' => 'The creator cannot approve their own variance.',
        ])->assertForbidden();

        $approver = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($approver)->post(route('supplier-invoices.approve', $invoice), [
            'approval_notes' => 'Supplier confirmed an urgent price surcharge and the second unit is in transit.',
        ])->assertRedirect();
        $invoice->refresh();
        $this->assertSame('registered', $invoice->status);
        $this->assertSame('approved', $invoice->matching_status);
        $this->assertSame($approver->id, $invoice->approved_by);
        $this->assertNotNull($invoice->approved_at);
        $this->assertNotNull($invoice->accountingEntry()->first());
    }

    public function test_transport_is_invoiced_once_across_partial_invoices_and_can_be_credited(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Freight Supplier SL', 'country' => 'ES']);
        $product = Product::create(['type' => 'service', 'sku' => 'FREIGHT-SERVICE', 'name' => 'Service', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 150, 'cost_price' => 100, 'track_stock' => false, 'is_active' => true]);
        $this->actingAs($user);

        $this->post(route('purchases.store'), [
            'type' => 'purchase_order', 'supplier_id' => $supplier->id, 'issue_date' => '2026-07-13', 'shipping_cost' => 30,
            'lines' => [['product_id' => $product->id, 'description' => 'Service', 'quantity' => 2, 'unit_price' => 100, 'discount_percent' => 0, 'tax_percent' => 21]],
        ])->assertRedirect();
        $order = PurchaseDocument::where('type', 'purchase_order')->firstOrFail();
        $line = $order->lines()->firstOrFail();

        $this->post(route('purchases.invoice', $order), [
            'supplier_number' => 'FREIGHT-1', 'issue_date' => '2026-07-13',
            'lines' => [['line_id' => $line->id, 'quantity' => 1, 'unit_price' => 100]],
        ])->assertRedirect();
        $firstInvoice = PurchaseDocument::where('type', 'supplier_invoice')->firstOrFail();
        $this->assertSame('30.00', $firstInvoice->shipping_cost);
        $this->assertSame('157.30', $firstInvoice->amount);
        $this->assertSame('partially_invoiced', $order->refresh()->invoicing_status);

        $this->post(route('purchases.invoice', $order), [
            'supplier_number' => 'FREIGHT-2', 'issue_date' => '2026-07-14',
            'lines' => [['line_id' => $line->id, 'quantity' => 1, 'unit_price' => 100]],
        ])->assertRedirect();
        $secondInvoice = PurchaseDocument::where('type', 'supplier_invoice')->latest('id')->firstOrFail();
        $this->assertSame('0.00', $secondInvoice->shipping_cost);
        $this->assertSame('invoiced', $order->refresh()->invoicing_status);

        $this->post(route('supplier-invoices.credit-note', $firstInvoice), [
            'supplier_number' => 'FREIGHT-CREDIT', 'issue_date' => '2026-07-15', 'shipping_cost' => 10, 'lines' => [],
        ])->assertRedirect();
        $credit = PurchaseDocument::where('type', 'supplier_credit_note')->firstOrFail();
        $this->assertSame('-10.00', $credit->shipping_cost);
        $this->assertSame('-12.10', $credit->amount);
        $this->assertNotNull($credit->accountingEntry()->first());
        $this->assertSame('partially_invoiced', $order->refresh()->invoicing_status);
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
        $this->actingAs($this->financeUser());
        $this->post(route('supplier-invoices.payments.store', $firstInvoice), ['paid_at' => '2026-07-14', 'amount' => 969, 'method' => 'bank_transfer'])->assertSessionHasErrors('amount');
        $this->post(route('supplier-invoices.payments.store', $firstInvoice), ['paid_at' => '2026-07-14', 'amount' => 968, 'method' => 'bank_transfer'])->assertRedirect();
        $this->assertSame('paid', $firstInvoice->refresh()->status);

        $this->actingAs($user)->post(route('purchases.return', $order), [
            'warehouse_id' => $warehouse->id, 'supplier_return_number' => 'DEV-SUP-1', 'quantities' => [$line->id => 1],
        ])->assertRedirect();
        $this->assertDatabaseHas('goods_returns', ['purchase_document_id' => $order->id, 'supplier_return_number' => 'DEV-SUP-1']);
        $this->assertSame('1.000', $line->refresh()->returned_quantity);
        $this->assertSame('partially_invoiced', $order->refresh()->invoicing_status);
        $this->assertDatabaseHas('stock_movements', ['purchase_document_id' => $order->id, 'type' => 'purchase_return']);
    }

    public function test_a_serialized_unit_can_only_be_returned_against_its_originating_purchase_order(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $warehouse = Warehouse::create(['code' => 'TRACE', 'name' => 'Traceability warehouse']);
        $product = Product::create([
            'type' => 'product',
            'sku' => 'TRACE-SERIAL',
            'name' => 'Traceable inverter',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 1000,
            'cost_price' => 700,
            'track_stock' => true,
            'track_serials' => true,
            'is_active' => true,
        ]);
        $this->actingAs($user);

        $orders = collect(['A', 'B'])->map(function (string $suffix) use ($user, $product): PurchaseDocument {
            $supplier = Supplier::create(['legal_name' => "Supplier {$suffix}", 'country' => 'ES']);
            $order = PurchaseDocument::create([
                'type' => 'purchase_order',
                'supplier_id' => $supplier->id,
                'created_by' => $user->id,
                'number' => "PC-TRACE-{$suffix}",
                'status' => 'ordered',
                'issue_date' => '2026-08-11',
                'subtotal' => 700,
                'tax_amount' => 147,
                'amount' => 847,
            ]);
            $order->lines()->create([
                'product_id' => $product->id,
                'position' => 1,
                'description' => $product->name,
                'quantity' => 1,
                'unit_price' => 700,
                'discount_percent' => 0,
                'tax_percent' => 21,
                'subtotal' => 700,
                'tax_amount' => 147,
                'total' => 847,
            ]);

            return $order;
        });

        foreach ($orders as $index => $order) {
            $line = $order->lines()->firstOrFail();
            $this->post(route('purchases.receive', $order), [
                'warehouse_id' => $warehouse->id,
                'quantities' => [$line->id => 1],
                'serials' => [$line->id => 'TRACE-'.($index + 1)],
            ])->assertRedirect();
        }

        $firstSerial = SerialNumber::where('serial_number', 'TRACE-1')->firstOrFail();
        $this->assertSame($orders[0]->id, $firstSerial->origin_purchase_document_id);

        $secondOrder = $orders[1];
        $secondLine = $secondOrder->lines()->firstOrFail();
        $this->post(route('purchases.return', $secondOrder), [
            'warehouse_id' => $warehouse->id,
            'quantities' => [$secondLine->id => 1],
            'serials' => [$secondLine->id => 'TRACE-1'],
        ])->assertSessionHasErrors('lines');

        $this->assertSame('0.000', $secondLine->refresh()->returned_quantity);
        $this->assertSame('available', $firstSerial->refresh()->status);

        $this->post(route('purchases.return', $secondOrder), [
            'warehouse_id' => $warehouse->id,
            'quantities' => [$secondLine->id => 1],
            'serials' => [$secondLine->id => 'TRACE-2'],
        ])->assertRedirect();

        $this->assertSame('returned', SerialNumber::where('serial_number', 'TRACE-2')->value('status'));
    }

    private function financeUser(): User
    {
        return User::factory()->create([
            'role' => 'commercial',
            'permission_profile_id' => PermissionProfile::where('slug', 'finance')->firstOrFail()->id,
        ]);
    }
}
