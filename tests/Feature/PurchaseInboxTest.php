<?php

namespace Tests\Feature;

use App\Ai\Agents\SupplierInvoiceExtractor;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\PurchaseInboxDocument;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_private_inbox_document_is_deduplicated_extracted_assigned_and_converted(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Inbox Solar SL', 'tax_id' => 'B87654321', 'country' => 'ES', 'is_active' => true]);
        $this->actingAs($user);

        $file = UploadedFile::fake()->image('inbox-invoice.png', 900, 1200);
        $this->post(route('purchase-inbox.store'), ['documents' => [$file]])->assertRedirect();
        $inbox = PurchaseInboxDocument::firstOrFail();
        Storage::disk('local')->assertExists($inbox->path);
        $this->get(route('purchase-inbox.download', $inbox))->assertOk();

        $duplicate = UploadedFile::fake()->createWithContent('duplicate.png', Storage::disk('local')->get($inbox->path));
        $this->post(route('purchase-inbox.store'), ['documents' => [$duplicate]])->assertSessionHasErrors('documents');
        $this->assertSame(1, PurchaseInboxDocument::count());

        SupplierInvoiceExtractor::fake([[
            'supplier_name' => 'Inbox Solar SL', 'supplier_tax_id' => 'B87654321', 'invoice_number' => 'IS-100',
            'issue_date' => '2026-07-13', 'due_date' => '2026-08-12', 'subtotal' => 100, 'tax_amount' => 21,
            'total' => 121, 'currency' => 'EUR', 'confidence' => 0.97,
        ]]);
        $this->post(route('purchase-inbox.extract', $inbox))->assertRedirect();
        $inbox->refresh();
        $this->assertSame('supplier_invoice', $inbox->classification);
        $this->assertSame('ready', $inbox->status);
        $this->assertSame($supplier->id, $inbox->supplier_id);

        $this->post(route('purchase-inbox.convert', $inbox), [
            'classification' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'purchase_order_id' => 'none',
            'supplier_number' => 'IS-100', 'issue_date' => '2026-07-13', 'due_date' => '2026-08-12',
            'subtotal' => 100, 'tax_amount' => 21, 'total' => 121, 'notes' => 'Reviewed from inbox.',
        ])->assertRedirect();

        $invoice = PurchaseDocument::where('type', 'supplier_invoice')->firstOrFail();
        $this->assertSame('121.00', $invoice->amount);
        $this->assertNotNull($invoice->accountingEntry()->first());
        $this->assertSame('verified', $invoice->attachment()->firstOrFail()->extraction_status);
        $this->assertSame('converted', $inbox->refresh()->status);
        $this->assertSame($invoice->id, $inbox->purchase_document_id);
        $this->post(route('purchase-inbox.convert', $inbox), [])->assertUnprocessable();
    }

    public function test_an_inbox_invoice_can_only_bind_to_a_matching_order_and_credit_notes_post_negative_amounts(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Order Supplier SL', 'tax_id' => 'B11111111', 'country' => 'ES', 'is_active' => true]);
        $other = Supplier::create(['legal_name' => 'Other Supplier SL', 'tax_id' => 'B22222222', 'country' => 'ES', 'is_active' => true]);
        $product = Product::create(['type' => 'service', 'sku' => 'SERV-INBOX', 'name' => 'Commissioning', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 150, 'cost_price' => 100, 'track_stock' => false, 'track_serials' => false, 'is_active' => true]);
        $this->actingAs($user);
        $this->post(route('purchases.store'), [
            'type' => 'purchase_order', 'supplier_id' => $supplier->id, 'issue_date' => '2026-07-13',
            'lines' => [['product_id' => $product->id, 'description' => 'Commissioning', 'quantity' => 1, 'unit_price' => 100, 'discount_percent' => 0, 'tax_percent' => 21]],
        ])->assertRedirect();
        $order = PurchaseDocument::where('type', 'purchase_order')->firstOrFail();

        $this->post(route('purchase-inbox.store'), ['documents' => [UploadedFile::fake()->image('order-invoice.png')]])->assertRedirect();
        $invoiceInbox = PurchaseInboxDocument::firstOrFail();
        $payload = ['classification' => 'supplier_invoice', 'supplier_id' => $other->id, 'purchase_order_id' => $order->id, 'supplier_number' => 'ORDER-1', 'issue_date' => '2026-07-13', 'subtotal' => 100, 'tax_amount' => 21, 'total' => 121];
        $this->post(route('purchase-inbox.convert', $invoiceInbox), $payload)->assertSessionHasErrors('purchase_order_id');
        $this->post(route('purchase-inbox.convert', $invoiceInbox), [...$payload, 'supplier_id' => $supplier->id, 'total' => 120])->assertSessionHasErrors('total');
        $this->post(route('purchase-inbox.convert', $invoiceInbox), [...$payload, 'supplier_id' => $supplier->id])->assertRedirect();
        $this->assertSame('invoiced', $order->refresh()->invoicing_status);

        $this->post(route('purchase-inbox.store'), ['documents' => [UploadedFile::fake()->image('credit-note.png', 300, 500)]])->assertRedirect()->assertSessionHasNoErrors();
        $creditInbox = PurchaseInboxDocument::latest('id')->firstOrFail();
        $this->post(route('purchase-inbox.convert', $creditInbox), [
            'classification' => 'supplier_credit_note', 'supplier_id' => $supplier->id, 'purchase_order_id' => 'none',
            'supplier_number' => 'CREDIT-1', 'issue_date' => '2026-07-13', 'subtotal' => 50, 'tax_amount' => 10.50, 'total' => 60.50,
        ])->assertRedirect();
        $credit = PurchaseDocument::where('type', 'supplier_credit_note')->firstOrFail();
        $this->assertSame('-60.50', $credit->amount);
        $this->assertNotNull($credit->accountingEntry()->first());
        $this->assertEquals(
            $credit->accountingEntry->lines->sum('debit'),
            $credit->accountingEntry->lines->sum('credit'),
        );
    }
}
