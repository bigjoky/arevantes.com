<?php

namespace Tests\Feature;

use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_contacts_support_responsibilities_signatories_and_primary_promotion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = Supplier::create(['legal_name' => 'Supply SL', 'country' => 'ES', 'payment_terms_days' => 30]);

        $this->actingAs($admin)->post(route('supplier-intelligence.contacts.store', $supplier), [
            'first_name' => 'Ana', 'roles' => ['billing'], 'email' => 'billing@example.test', 'is_primary' => false,
        ])->assertRedirect();
        $this->post(route('supplier-intelligence.contacts.store', $supplier), [
            'first_name' => 'Luis', 'roles' => ['legal_representative', 'purchasing'], 'document_type' => 'nif',
            'document_number' => '12345678Z', 'is_authorized_signatory' => true, 'is_primary' => false,
        ])->assertRedirect();

        $ana = SupplierContact::where('supplier_id', $supplier->id)->where('first_name', 'Ana')->sole();
        $this->assertTrue($ana->is_primary);
        $this->delete(route('supplier-intelligence.contacts.destroy', [$supplier, $ana]))->assertRedirect();
        $this->assertTrue(SupplierContact::where('supplier_id', $supplier->id)->sole()->is_primary);
    }

    public function test_purchase_documents_freeze_supplier_identity_and_derived_documents_inherit_it(): void
    {
        $supplier = Supplier::create([
            'legal_name' => 'Original Supply SL', 'tax_id' => 'B12345678', 'country' => 'ES', 'payment_terms_days' => 45,
        ]);
        $supplier->contacts()->create([
            'first_name' => 'Marta', 'roles' => ['billing'], 'email' => 'billing@example.test', 'is_primary' => true,
        ]);

        $request = PurchaseDocument::create([
            'type' => 'purchase_rfq', 'supplier_id' => $supplier->id, 'number' => 'RFQ-SNAPSHOT-1', 'status' => 'draft',
            'issue_date' => today(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121,
        ]);
        $supplier->update(['legal_name' => 'Renamed Supply SL', 'payment_terms_days' => 10]);
        $order = PurchaseDocument::create([
            'type' => 'purchase_order', 'source_document_id' => $request->id, 'supplier_id' => $supplier->id,
            'number' => 'PO-SNAPSHOT-1', 'status' => 'ordered', 'issue_date' => today(), 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121,
        ]);

        $this->assertSame('Original Supply SL', $request->supplier_snapshot['legal_name']);
        $this->assertSame(45, $request->supplier_snapshot['payment_terms_days']);
        $this->assertSame('billing@example.test', $request->supplier_snapshot['billing_contact']['email']);
        $this->assertSame($request->supplier_snapshot, $order->supplier_snapshot);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('purchases.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('supplierIdentity.legal_name', 'Original Supply SL')
                ->where('supplierIdentity.payment_terms_days', 45));

        $html = view('pdf.purchase-document', [
            'document' => $order->load(['supplier', 'destinationWarehouse', 'lines']),
            'company' => ['legal_name' => 'Arevantes SL', 'trade_name' => null],
            'supplierIdentity' => $order->supplierIdentity(),
        ])->render();
        $this->assertStringContainsString('Original Supply SL', $html);
        $this->assertStringNotContainsString('Renamed Supply SL', $html);
    }
}
