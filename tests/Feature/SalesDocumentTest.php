<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\Person;
use App\Models\SalesDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_direct_invoice_can_be_created_edited_issued_and_accounted(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Direct', 'last_name' => 'Customer', 'tax_id' => '12345678Z']);
        $installation = Installation::create(['person_id' => $customer->id, 'service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $this->actingAs($user);

        $this->get(route('sales-documents.create', ['installation' => $installation->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sales/form')
                ->where('defaultCustomerId', $customer->id)
                ->where('defaultInstallationId', $installation->id));

        $this->post(route('sales-documents.store'), [
            'type' => 'invoice', 'person_id' => $customer->id, 'installation_id' => $installation->id,
            'issue_date' => '2026-07-13', 'due_date' => '2026-08-12', 'notes' => 'Direct billing',
            'lines' => [[
                'description' => 'Electrical work', 'quantity' => 2, 'unit_price' => 100,
                'discount_percent' => 10, 'tax_percent' => 21,
            ]],
        ])->assertRedirect();

        $invoice = SalesDocument::firstOrFail();
        $this->assertStringStartsWith('DRAFT-INV-', $invoice->number);
        $this->assertNull($invoice->quote_id);
        $this->assertSame('200.00', $invoice->subtotal);
        $this->assertSame('20.00', $invoice->discount_amount);
        $this->assertSame('37.80', $invoice->tax_amount);
        $this->assertSame('217.80', $invoice->amount);

        $this->get(route('sales-documents.edit', $invoice))->assertOk()->assertInertia(fn (Assert $page) => $page->component('sales/form')->where('document.id', $invoice->id));
        $this->put(route('sales-documents.update', $invoice), [
            'type' => 'invoice', 'person_id' => $customer->id, 'installation_id' => $installation->id,
            'issue_date' => '2026-07-14', 'due_date' => '2026-08-13', 'notes' => 'Updated direct billing',
            'lines' => [[
                'description' => 'Electrical work and certification', 'quantity' => 1, 'unit_price' => 500,
                'discount_percent' => 0, 'tax_percent' => 21,
            ]],
        ])->assertRedirect(route('sales-documents.show', $invoice));
        $this->assertSame('605.00', $invoice->refresh()->amount);
        $this->assertSame('Electrical work and certification', $invoice->lines()->firstOrFail()->description);

        $this->post(route('sales-documents.issue', $invoice), ['issue_date' => '2026-07-14', 'due_date' => '2026-08-13'])->assertRedirect();
        $this->assertSame('issued', $invoice->refresh()->status);
        $this->assertNotNull($invoice->accountingEntry);
        $this->put(route('sales-documents.update', $invoice), [])->assertUnprocessable();
        $this->delete(route('sales-documents.destroy', $invoice))->assertUnprocessable();
    }

    public function test_a_direct_draft_can_be_deleted_and_installation_must_match_customer(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $other = Person::create(['category' => 'customer', 'first_name' => 'Other']);
        $installation = Installation::create(['person_id' => $other->id, 'service_type' => 'hvac', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Frio 2']);
        $payload = [
            'type' => 'delivery_note', 'person_id' => $customer->id, 'issue_date' => '2026-07-13',
            'lines' => [['description' => 'Material delivery', 'quantity' => 1, 'unit_price' => 50, 'discount_percent' => 0, 'tax_percent' => 21]],
        ];
        $this->actingAs($user);

        $this->post(route('sales-documents.store'), [...$payload, 'installation_id' => $installation->id])->assertUnprocessable();
        $this->post(route('sales-documents.store'), $payload)->assertRedirect();
        $document = SalesDocument::firstOrFail();
        $this->delete(route('sales-documents.destroy', $document))->assertRedirect(route('sales.index'));
        $this->assertDatabaseMissing('sales_documents', ['id' => $document->id]);
    }

    public function test_an_order_converts_partially_to_delivery_notes_and_invoices_with_line_traceability(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Chain']);
        $this->actingAs($user)->post(route('sales-documents.store'), [
            'type' => 'order', 'person_id' => $customer->id, 'issue_date' => '2026-07-13',
            'lines' => [['description' => 'Commissioning services', 'quantity' => 10, 'unit_price' => 100, 'discount_percent' => 0, 'tax_percent' => 21]],
        ])->assertRedirect();
        $order = SalesDocument::where('type', 'order')->firstOrFail();
        $line = $order->lines()->firstOrFail();

        $this->post(route('sales-documents.convert', $order), ['type' => 'delivery_note', 'quantities' => [$line->id => 4]])->assertRedirect();
        $delivery = SalesDocument::where('type', 'delivery_note')->firstOrFail();
        $this->assertSame($order->id, $delivery->source_document_id);
        $this->assertSame($line->id, $delivery->lines()->firstOrFail()->source_line_id);
        $this->assertSame('4.000', $delivery->lines()->firstOrFail()->quantity);
        $this->assertSame('484.00', $delivery->amount);
        $this->assertSame('pending', $order->refresh()->fulfillment_status);
        $this->get(route('sales-documents.edit', $delivery))->assertUnprocessable();

        $this->post(route('sales-documents.convert', $order), ['type' => 'invoice', 'quantities' => [$line->id => 3]])->assertRedirect();
        $invoice = SalesDocument::where('type', 'invoice')->firstOrFail();
        $this->assertSame('363.00', $invoice->amount);
        $this->assertSame('partial', $order->refresh()->billing_status);
        $this->post(route('sales-documents.convert', $order), ['type' => 'delivery_note', 'quantities' => [$line->id => 7]])->assertSessionHasErrors("quantities.{$line->id}");

        $this->post(route('sales-documents.convert', $order), ['type' => 'delivery_note', 'quantities' => [$line->id => 6]])->assertRedirect();
        $secondDelivery = SalesDocument::where('type', 'delivery_note')->latest('id')->firstOrFail();
        $this->assertSame('pending', $order->refresh()->fulfillment_status);
        $this->get(route('sales-documents.show', $order))->assertInertia(fn (Assert $page) => $page
            ->where('conversion.lines.0.allocated_delivery_quantity', 10)
            ->where('conversion.lines.0.delivered_quantity', 0)
            ->where('conversion.lines.0.invoiced_quantity', 3)
            ->where('conversion.lines.0.remaining_delivery_quantity', 0)
            ->where('conversion.lines.0.remaining_invoice_quantity', 7)
            ->has('conversion.children', 3));

        $this->delete(route('sales-documents.destroy', $secondDelivery))->assertRedirect();
        $this->assertSame('pending', $order->refresh()->fulfillment_status);
        $this->delete(route('sales-documents.destroy', $order))->assertUnprocessable();

        $this->post(route('sales-documents.convert', $delivery), ['type' => 'invoice', 'quantities' => [$delivery->lines()->firstOrFail()->id => 4]])->assertSessionHasErrors('type');
        $this->post(route('sales-documents.dispatch', $delivery), [])->assertRedirect();
        $this->assertSame('delivered', $delivery->refresh()->status);
        $this->assertSame('partial', $order->refresh()->fulfillment_status);
        $this->post(route('sales-documents.convert', $delivery), ['type' => 'invoice', 'quantities' => [$delivery->lines()->firstOrFail()->id => 4]])->assertRedirect();
        $deliveryInvoice = SalesDocument::where('type', 'invoice')->latest('id')->firstOrFail();
        $this->assertSame($delivery->id, $deliveryInvoice->source_document_id);
        $this->assertSame('complete', $delivery->refresh()->billing_status);
        $this->assertSame('partial', $order->refresh()->billing_status);
        $this->get(route('sales-documents.show', $order))->assertInertia(fn (Assert $page) => $page
            ->where('conversion.lines.0.invoiced_quantity', 7)
            ->where('conversion.lines.0.remaining_invoice_quantity', 3));

        $this->post(route('sales-documents.convert', $order), ['type' => 'delivery_note', 'quantities' => [$line->id => 6]])->assertRedirect();
        $remainingDelivery = SalesDocument::where('type', 'delivery_note')->latest('id')->firstOrFail();
        $remainingLine = $remainingDelivery->lines()->firstOrFail();
        $this->post(route('sales-documents.dispatch', $remainingDelivery), [])->assertRedirect();
        $this->assertSame('complete', $order->refresh()->fulfillment_status);
        $this->post(route('sales-documents.convert', $remainingDelivery), ['type' => 'invoice', 'quantities' => [$remainingLine->id => 4]])->assertSessionHasErrors('quantities');
        $this->post(route('sales-documents.convert', $remainingDelivery), ['type' => 'invoice', 'quantities' => [$remainingLine->id => 3]])->assertRedirect();
        $this->assertSame('partial', $remainingDelivery->refresh()->billing_status);
        $this->assertSame('complete', $order->refresh()->billing_status);
    }
}
