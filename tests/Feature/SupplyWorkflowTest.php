<?php

namespace Tests\Feature;

use App\Mail\PurchaseDocumentMail;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\PurchaseSetting;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupplyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_purchase_request_can_be_sent_cancelled_and_audited(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'commercial']);
        [$supplier, $product] = $this->catalog();

        $this->actingAs($user)->post(route('purchases.store'), [
            'type' => 'purchase_rfq',
            'supplier_id' => $supplier->id,
            'issue_date' => '2026-07-25',
            'expected_delivery_date' => '2026-08-01',
            'lines' => [[
                'product_id' => $product->id,
                'description' => 'Solar panel',
                'quantity' => 2,
                'unit_price' => 100,
                'discount_percent' => 0,
                'tax_percent' => 21,
            ]],
        ])->assertRedirect();

        $request = PurchaseDocument::firstOrFail();
        $this->post(route('purchases.send', $request), [
            'recipient_email' => 'purchases@supplier.test',
            'message' => 'Please confirm price and delivery.',
        ])->assertRedirect();

        Mail::assertSent(PurchaseDocumentMail::class);
        $this->assertSame('sent', $request->refresh()->status);
        $this->assertNotNull($request->sent_at);
        $this->assertDatabaseHas('purchase_document_events', ['purchase_document_id' => $request->id, 'event' => 'sent']);

        $this->post(route('purchases.cancel', $request), ['reason' => 'Project scope changed.'])->assertRedirect();
        $this->assertSame('cancelled', $request->refresh()->status);
        $this->assertDatabaseHas('purchase_document_events', ['purchase_document_id' => $request->id, 'event' => 'cancelled']);
    }

    public function test_purchase_capabilities_are_separated_and_settings_are_admin_only(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        [$supplier, $product] = $this->catalog();
        $payload = [
            'type' => 'purchase_order',
            'supplier_id' => $supplier->id,
            'issue_date' => '2026-07-25',
            'lines' => [[
                'product_id' => $product->id,
                'description' => 'Solar panel',
                'quantity' => 1,
                'unit_price' => 100,
                'discount_percent' => 0,
                'tax_percent' => 21,
            ]],
        ];

        $this->actingAs($technician)->post(route('purchases.store'), $payload)->assertForbidden();
        $this->patch(route('purchase-settings.update'), [
            'matching_amount_tolerance' => 5,
            'matching_percent_tolerance' => 2,
            'shipping_tax_percent' => 10,
        ])->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('purchase-settings.update'), [
            'matching_amount_tolerance' => 5,
            'matching_percent_tolerance' => 2,
            'shipping_tax_percent' => 10,
        ])->assertRedirect();

        $settings = PurchaseSetting::current();
        $this->assertSame('5.00', $settings->matching_amount_tolerance);
        $this->assertSame('2.00', $settings->matching_percent_tolerance);
        $this->assertSame('10.00', $settings->shipping_tax_percent);
    }

    private function catalog(): array
    {
        $supplier = Supplier::create([
            'legal_name' => 'Workflow Supplier SL',
            'email' => 'purchases@supplier.test',
            'country' => 'ES',
            'is_active' => true,
        ]);
        $product = Product::create([
            'type' => 'product',
            'sku' => 'SUPPLY-WORKFLOW',
            'name' => 'Solar panel',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 150,
            'cost_price' => 100,
            'track_stock' => true,
            'track_serials' => false,
            'is_active' => true,
        ]);

        return [$supplier, $product];
    }
}
