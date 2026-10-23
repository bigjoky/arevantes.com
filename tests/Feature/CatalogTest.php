<?php

namespace Tests\Feature;

use App\Models\Installation;
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

    public function test_catalog_configuration_has_an_independent_authorized_destination(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'commercial']))->get('/catalog/settings')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('catalog/index')->where('initialTab', 'settings'));
        $this->actingAs(User::factory()->create(['role' => 'technician']))->get('/catalog/settings')->assertForbidden();
    }

    public function test_commercial_users_can_view_the_catalog(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);

        $this->actingAs($user)->get(route('catalog.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('supply/products')->has('products.data')->has('editor.units', 5)->has('editor.taxRates', 4));

        $this->actingAs($user)->get(route('suppliers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('supply/suppliers')->has('suppliers.data'));
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
            'business_lines' => ['solar', 'electrical', 'security'],
        ])->assertRedirect();

        $product = Product::where('sku', 'INV-5K')->firstOrFail();
        $this->assertSame(['solar', 'electrical', 'security'], $product->business_lines);
        $this->actingAs($user)->patch(route('catalog.products.update', $product), [
            'type' => 'product', 'sku' => 'INV-5K', 'name' => 'Solar inverter 5 kW updated',
            'unit_id' => $unit->id, 'tax_rate_id' => $tax->id, 'sale_price' => 1300,
            'cost_price' => 900, 'sale_account_code' => '701', 'purchase_account_code' => '601', 'track_stock' => true, 'track_serials' => true, 'is_active' => true,
            'business_lines_submitted' => true,
        ])->assertRedirect();

        $this->assertSame('Solar inverter 5 kW updated', $product->fresh()->name);
        $this->assertSame('701', $product->fresh()->sale_account_code);
        $this->assertSame([], $product->fresh()->business_lines);
    }

    public function test_a_composite_product_reuses_catalog_items_and_exposes_a_resolved_quote_template(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $unit = Unit::where('code', 'unit')->firstOrFail();
        $tax = TaxRate::where('is_default', true)->firstOrFail();
        $panel = Product::create([
            'type' => 'product', 'sku' => 'Z-PANEL', 'name' => 'Z Panel', 'unit_id' => $unit->id, 'tax_rate_id' => $tax->id,
            'sale_price' => 200, 'cost_price' => 100, 'track_stock' => true, 'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('catalog.products.store'), [
            'type' => 'product', 'sku' => 'A-KIT', 'name' => 'A Solar kit', 'unit_id' => $unit->id, 'tax_rate_id' => $tax->id,
            'sale_price' => 1000, 'cost_price' => 0, 'sale_account_code' => '700', 'purchase_account_code' => '600',
            'track_stock' => true, 'track_serials' => true, 'is_active' => true,
            'components' => [[
                'component_product_id' => $panel->id, 'quantity' => 4, 'fulfillment_kind' => 'stock', 'visibility' => 'detailed',
            ]],
        ])->assertRedirect();

        $kit = Product::where('sku', 'A-KIT')->firstOrFail();
        $this->assertSame('400.00', $kit->cost_price);
        $this->assertFalse($kit->track_stock);
        $this->assertFalse($kit->track_serials);
        $this->assertDatabaseHas('product_components', ['parent_product_id' => $kit->id, 'component_product_id' => $panel->id, 'quantity' => 4]);
        $this->get(route('quotes.create'))->assertInertia(fn (Assert $page) => $page
            ->where('products.0.id', $kit->id)
            ->where('products.0.resolved_components.0.product_id', $panel->id)
            ->where('products.0.resolved_components.0.quantity', 4));
    }

    public function test_a_supplier_can_be_managed(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user)->post(route('catalog.suppliers.store'), [
            'legal_name' => 'Solar Supply SL', 'tax_id' => 'B12345678', 'email' => 'sales@example.com',
            'country' => 'ES', 'province' => 'Córdoba', 'city' => 'Lucena', 'locality' => 'Las Navas del Selpillar',
            'postal_code' => '14900', 'payment_terms_days' => 30, 'is_active' => true,
        ])->assertRedirect();

        $supplier = Supplier::where('tax_id', 'B12345678')->firstOrFail();
        $this->assertSame('Las Navas del Selpillar', $supplier->locality);
        $this->assertSame('ES', $supplier->purchaseSnapshot()['country']);
        $this->assertSame('Las Navas del Selpillar', $supplier->purchaseSnapshot()['locality']);
        $this->actingAs($user)->delete(route('catalog.suppliers.destroy', $supplier))->assertRedirect();
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_technicians_cannot_manage_the_catalog(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $this->actingAs($user)->get(route('catalog.index'))->assertForbidden();
        $this->actingAs($user)->get(route('suppliers.index'))->assertForbidden();
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

    public function test_quote_profitability_compares_net_expected_margin_with_live_project_actuals(): void
    {
        config(['work.default_hourly_cost' => 50]);
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Lucía']);
        $installation = Installation::create([
            'person_id' => $customer->id,
            'service_type' => 'solar',
            'site_address_line_1' => 'Calle Solar 1',
            'estimated_budget' => 1089,
        ]);
        $quote = Quote::create([
            'installation_id' => $installation->id,
            'person_id' => $customer->id,
            'created_by' => $user->id,
            'number' => 'PRE-MARGIN-1',
            'status' => 'accepted',
            'issue_date' => '2026-08-15',
            'subtotal' => 1000,
            'discount_amount' => 100,
            'tax_amount' => 189,
            'amount' => 1089,
            'expected_margin_amount' => 600,
        ]);
        $installation->materialRequirements()->create([
            'quote_id' => $quote->id,
            'source' => 'quote',
            'description' => 'Planned materials',
            'planned_quantity' => 1,
            'planned_unit_cost' => 200,
        ]);
        $workOrder = $installation->workOrders()->create([
            'created_by' => $user->id,
            'number' => 'WO-MARGIN-1',
            'title' => 'Installation',
            'status' => 'in_progress',
            'estimated_duration_minutes' => 120,
        ]);
        $workOrder->timeEntries()->create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'started_at' => '2026-08-15 08:00:00',
            'ended_at' => '2026-08-15 10:00:00',
            'duration_minutes' => 120,
            'hourly_cost' => 50,
        ]);
        SalesDocument::create([
            'type' => 'invoice',
            'quote_id' => $quote->id,
            'installation_id' => $installation->id,
            'person_id' => $customer->id,
            'created_by' => $user->id,
            'number' => 'FAC-MARGIN-1',
            'status' => 'issued',
            'issue_date' => '2026-08-15',
            'subtotal' => 800,
            'discount_amount' => 50,
            'tax_amount' => 157.50,
            'amount' => 907.50,
            'customer_snapshot' => [],
        ]);

        $this->actingAs($user)->get(route('quotes.show', $quote))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('quote.actual_margin_amount', '650.00')
                ->where('profitability.planned_revenue', 900)
                ->where('profitability.planned_cost', 300)
                ->where('profitability.planned_margin', 600)
                ->where('profitability.actual_revenue', 750)
                ->where('profitability.actual_cost', 100)
                ->where('profitability.actual_margin', 650)
                ->where('profitability.margin_variance', 50)
                ->where('profitability.has_actuals', true));

        $this->assertNull($quote->fresh()->actual_margin_amount);
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

    public function test_a_billing_milestone_can_use_an_exact_agreed_amount(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'María', 'tax_id' => '12345678Z']);
        $quote = Quote::create([
            'person_id' => $customer->id, 'created_by' => $user->id, 'number' => 'PRE-2026-00101', 'status' => 'accepted',
            'issue_date' => '2026-08-09', 'subtotal' => 1000, 'discount_amount' => 0, 'tax_amount' => 210, 'amount' => 1210,
            'customer_snapshot' => $customer->only(['first_name', 'last_name', 'company_name', 'tax_id']),
        ]);
        $quote->lines()->create(['position' => 1, 'description' => 'Solar installation', 'quantity' => 1, 'unit_price' => 1000, 'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 1000, 'discount_amount' => 0, 'tax_amount' => 210, 'total' => 1210]);

        $this->actingAs($user)->post(route('quotes.convert', $quote), [
            'type' => 'invoice', 'billing_percentage' => 41.32, 'billing_amount' => 500, 'billing_label' => 'Agreed advance',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $advance = SalesDocument::firstOrFail();
        $this->assertSame('500.00', $advance->amount);
        $this->assertSame('41.32', $advance->billing_percentage);
        $this->assertSame('500.00', $advance->lines()->firstOrFail()->total);
        $this->get(route('quotes.show', $quote))->assertInertia(fn (Assert $page) => $page
            ->where('billing.allocated_amount', 500)
            ->where('billing.remaining_amount', 710)
            ->where('billing.remaining_percentage', 58.68));

        $this->post(route('quotes.convert', $quote), [
            'type' => 'invoice', 'billing_amount' => 710.01,
        ])->assertSessionHasErrors('billing_amount');
    }
}
