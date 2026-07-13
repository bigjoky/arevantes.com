<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Person;
use App\Models\Product;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_users_can_view_the_catalog(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);

        $this->actingAs($user)->get(route('catalog.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('catalog/index')
            ->has('units', 5)
            ->has('taxRates', 4)
            ->has('documentSeries', 11));
    }

    public function test_a_product_can_be_created_and_updated(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $unit = Unit::firstOrFail();
        $tax = TaxRate::where('is_default', true)->firstOrFail();

        $this->actingAs($user)->post(route('catalog.products.store'), [
            'type' => 'product', 'sku' => 'INV-5K', 'name' => 'Solar inverter 5 kW',
            'unit_id' => $unit->id, 'tax_rate_id' => $tax->id, 'sale_price' => 1250,
            'cost_price' => 900, 'sale_account_code' => '700', 'purchase_account_code' => '600', 'track_stock' => true, 'track_serials' => true, 'is_active' => true,
        ])->assertRedirect();

        $product = Product::where('sku', 'INV-5K')->firstOrFail();
        $this->actingAs($user)->patch(route('catalog.products.update', $product), [
            'type' => 'product', 'sku' => 'INV-5K', 'name' => 'Solar inverter 5 kW updated',
            'unit_id' => $unit->id, 'tax_rate_id' => $tax->id, 'sale_price' => 1300,
            'cost_price' => 900, 'sale_account_code' => '701', 'purchase_account_code' => '601', 'track_stock' => true, 'track_serials' => true, 'is_active' => true,
        ])->assertRedirect();

        $this->assertSame('Solar inverter 5 kW updated', $product->fresh()->name);
        $this->assertSame('701', $product->fresh()->sale_account_code);
    }

    public function test_a_supplier_can_be_managed(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user)->post(route('catalog.suppliers.store'), [
            'legal_name' => 'Solar Supply SL', 'tax_id' => 'B12345678', 'email' => 'sales@example.com',
            'country' => 'ES', 'payment_terms_days' => 30, 'is_active' => true,
        ])->assertRedirect();

        $supplier = Supplier::where('tax_id', 'B12345678')->firstOrFail();
        $this->actingAs($user)->delete(route('catalog.suppliers.destroy', $supplier))->assertRedirect();
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_technicians_cannot_manage_the_catalog(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $this->actingAs($user)->get(route('catalog.index'))->assertForbidden();
    }

    public function test_a_quote_calculates_lines_and_uses_the_document_series(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);

        $this->actingAs($user)->post(route('quotes.store'), [
            'person_id' => $customer->id,
            'issue_date' => '2026-07-12',
            'valid_until' => '2026-08-12',
            'lines' => [[
                'description' => 'Photovoltaic modules',
                'quantity' => 10,
                'unit_price' => 100,
                'discount_percent' => 10,
                'tax_percent' => 21,
            ]],
        ])->assertRedirect();

        $quote = Quote::with('lines')->firstOrFail();
        $this->assertSame('PRE-2026-00001', $quote->number);
        $this->assertSame('1000.00', $quote->subtotal);
        $this->assertSame('100.00', $quote->discount_amount);
        $this->assertSame('189.00', $quote->tax_amount);
        $this->assertSame('1089.00', $quote->amount);
        $this->assertCount(1, $quote->lines);
    }

    public function test_an_accepted_quote_converts_once_and_renders_a_pdf(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Lucia', 'tax_id' => '12345678Z']);
        $quote = Quote::create([
            'person_id' => $customer->id, 'created_by' => $user->id, 'number' => 'PRE-2026-00999', 'status' => 'accepted',
            'issue_date' => '2026-07-12', 'subtotal' => 1000, 'discount_amount' => 0, 'tax_amount' => 210, 'amount' => 1210,
            'customer_snapshot' => $customer->only(['first_name', 'last_name', 'company_name', 'tax_id']),
        ]);
        $quote->lines()->create(['position' => 1, 'description' => 'Solar installation', 'quantity' => 1, 'unit_price' => 1000, 'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 1000, 'discount_amount' => 0, 'tax_amount' => 210, 'total' => 1210]);

        $this->actingAs($user)->post(route('quotes.convert', $quote), ['type' => 'invoice'])->assertRedirect();
        $invoice = SalesDocument::firstOrFail();
        $this->assertStringStartsWith('DRAFT-INV-', $invoice->number);
        $this->assertCount(1, $invoice->lines);
        $this->actingAs($user)->post(route('quotes.convert', $quote), ['type' => 'invoice'])->assertRedirect(route('sales-documents.show', $invoice));
        $this->assertSame(1, SalesDocument::count());

        $this->actingAs($user)->post(route('sales-documents.issue', $invoice), [
            'issue_date' => '2026-07-12', 'due_date' => '2026-08-12',
        ])->assertRedirect();
        $this->assertSame('FAC-2026-00001', $invoice->refresh()->number);
        $this->assertSame('issued', $invoice->status);
        $this->assertNotNull($invoice->locked_at);
        $this->assertSame('AST-2026-000001', $invoice->accountingEntry()->firstOrFail()->number);

        $this->actingAs($user)->post(route('sales-documents.payments.store', $invoice), [
            'paid_at' => '2026-07-20', 'amount' => 500, 'method' => 'bank_transfer', 'reference' => 'TRX-1',
        ])->assertRedirect();
        $this->assertSame('partial', $invoice->refresh()->status);
        $this->assertSame('500.00', $invoice->paid_amount);
        $this->assertSame('AST-2026-000002', $invoice->payments()->firstOrFail()->accountingEntry()->firstOrFail()->number);

        $this->actingAs($user)->post(route('sales-documents.payments.store', $invoice), [
            'paid_at' => '2026-07-21', 'amount' => 710, 'method' => 'card',
        ])->assertRedirect();
        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame('1210.00', $invoice->paid_amount);
        $this->assertSame(3, JournalEntry::count());

        $this->actingAs($user)->post(route('sales-documents.credit-note', $invoice))->assertRedirect();
        $credit = SalesDocument::where('type', 'credit_note')->firstOrFail();
        $this->assertSame('-1210.00', $credit->amount);
        $this->assertSame($invoice->id, $credit->source_document_id);

        $this->actingAs($user)->get(route('sales-documents.pdf', $invoice))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_an_accepted_quote_can_be_invoiced_by_controlled_milestones(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Marta', 'tax_id' => '87654321X']);
        $quote = Quote::create([
            'person_id' => $customer->id, 'created_by' => $user->id, 'number' => 'PRE-2026-00100', 'status' => 'accepted',
            'issue_date' => '2026-07-13', 'subtotal' => 1000, 'discount_amount' => 0, 'tax_amount' => 210, 'amount' => 1210,
            'customer_snapshot' => $customer->only(['first_name', 'last_name', 'company_name', 'tax_id']),
        ]);
        $quote->lines()->create(['position' => 1, 'description' => 'Solar installation', 'quantity' => 1, 'unit_price' => 1000, 'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 1000, 'discount_amount' => 0, 'tax_amount' => 210, 'total' => 1210]);
        $this->actingAs($user);

        $this->post(route('quotes.convert', $quote), ['type' => 'invoice', 'billing_percentage' => 30, 'billing_label' => 'Contract advance'])->assertRedirect();
        $advance = SalesDocument::firstOrFail();
        $this->assertSame('30.00', $advance->billing_percentage);
        $this->assertSame('363.00', $advance->amount);
        $this->assertSame('300.00', $advance->lines()->firstOrFail()->subtotal);
        $this->post(route('sales-documents.issue', $advance), ['issue_date' => '2026-07-13', 'due_date' => '2026-07-20'])->assertRedirect();

        $this->post(route('quotes.convert', $quote), ['type' => 'invoice', 'billing_percentage' => 40, 'billing_label' => 'Installation progress'])->assertRedirect();
        $progress = SalesDocument::where('status', 'draft')->firstOrFail();
        $this->post(route('quotes.convert', $quote), ['type' => 'invoice', 'billing_percentage' => 31])->assertSessionHasErrors('billing_percentage');
        $this->delete(route('sales-documents.destroy', $progress))->assertRedirect(route('quotes.show', $quote));
        $this->delete(route('sales-documents.destroy', $advance))->assertUnprocessable();

        $this->post(route('quotes.convert', $quote), ['type' => 'invoice', 'billing_percentage' => 70, 'billing_label' => 'Final invoice'])->assertRedirect();
        $final = SalesDocument::where('status', 'draft')->firstOrFail();
        $this->assertSame('70.00', $final->billing_percentage);
        $this->assertSame('847.00', $final->amount);
        $this->assertSame('700.00', $final->lines()->firstOrFail()->subtotal);
        $this->post(route('sales-documents.issue', $final), ['issue_date' => '2026-07-14', 'due_date' => '2026-08-13'])->assertRedirect();

        $this->get(route('quotes.show', $quote))->assertInertia(fn (Assert $page) => $page
            ->where('billing.allocated_percentage', 100)
            ->where('billing.issued_percentage', 100)
            ->where('billing.remaining_percentage', 0)
            ->where('billing.allocated_amount', 1210)
            ->has('billing.documents', 2));
        $this->assertSame(2, JournalEntry::count());
        $this->assertSame(1210.0, (float) SalesDocument::where('type', 'invoice')->sum('amount'));
    }
}
