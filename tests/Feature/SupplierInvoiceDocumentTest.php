<?php

namespace Tests\Feature;

use App\Ai\Agents\SupplierInvoiceExtractor;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierInvoiceDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_original_supplier_invoice_is_private_deduplicated_extracted_and_verified(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Document Supplier SL', 'tax_id' => 'B12345678', 'country' => 'ES']);
        $invoice = PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'created_by' => $user->id, 'number' => 'FR-2026-00001', 'supplier_number' => 'DOC-100', 'status' => 'registered', 'issue_date' => '2026-07-13', 'due_date' => '2026-08-12', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121]);
        $duplicateInvoice = PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'created_by' => $user->id, 'number' => 'FR-2026-00002', 'supplier_number' => 'DOC-101', 'status' => 'registered', 'issue_date' => '2026-07-13', 'subtotal' => 50, 'tax_amount' => 10.50, 'amount' => 60.50]);
        $this->actingAs($user);

        $this->post(route('supplier-invoices.document.store', $invoice), ['document_file' => UploadedFile::fake()->image('supplier-invoice.png', 900, 1200)])->assertRedirect();
        $attachment = $invoice->attachment()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->get(route('supplier-invoices.document.download', $invoice))->assertOk();

        $duplicate = UploadedFile::fake()->createWithContent('duplicate.png', Storage::disk('local')->get($attachment->path));
        $this->post(route('supplier-invoices.document.store', $duplicateInvoice), ['document_file' => $duplicate])->assertSessionHasErrors('document_file');
        $this->assertNull($duplicateInvoice->attachment);

        SupplierInvoiceExtractor::fake([[
            'supplier_name' => 'Document Supplier SL', 'supplier_tax_id' => 'B12345678', 'invoice_number' => 'DOC-100',
            'issue_date' => '2026-07-13', 'due_date' => '2026-08-12', 'subtotal' => 100, 'tax_amount' => 21, 'total' => 121,
            'currency' => 'EUR', 'confidence' => 0.98,
        ]]);
        $this->post(route('supplier-invoices.document.extract', $invoice))->assertRedirect();
        $attachment->refresh();
        $this->assertSame('extracted', $attachment->extraction_status);
        $this->assertSame('DOC-100', $attachment->extracted_data['invoice_number']);
        $this->assertSame(0.98, $attachment->extracted_data['confidence']);
        SupplierInvoiceExtractor::assertPrompted(fn ($prompt) => $prompt->attachments->count() === 1);

        $this->post(route('supplier-invoices.document.verify', $invoice))->assertRedirect();
        $attachment->refresh();
        $this->assertSame('verified', $attachment->extraction_status);
        $this->assertSame($user->id, $attachment->verified_by);
        $this->assertNotNull($attachment->verified_at);
        $this->post(route('supplier-invoices.document.extract', $invoice))->assertUnprocessable();
    }
}
