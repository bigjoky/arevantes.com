<?php

namespace Tests\Feature;

use App\Models\DocumentSeries;
use App\Models\FiscalYear;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HistoricalEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_roles_are_catalogued_and_legacy_values_can_only_be_retained(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('staff.create'))->assertInertia(fn (Assert $page) => $page->where('staffRoles', config('staff.roles')));
        $this->post(route('staff.store'), ['first_name' => 'Ana', 'staff_role' => 'Anything'])->assertSessionHasErrors('staff_role');
        $this->post(route('staff.store'), ['first_name' => 'Ana', 'staff_role' => 'Installer'])->assertSessionHasNoErrors();
        $staff = Person::where('category', 'staff')->firstOrFail();
        $this->assertNull($staff->user);
        $staff->update(['staff_role' => 'Legacy role']);
        $this->patch(route('staff.update', $staff), ['first_name' => 'Ana', 'staff_role' => 'Legacy role'])->assertSessionHasNoErrors();
        $this->patch(route('staff.update', $staff), ['first_name' => 'Ana', 'staff_role' => 'Another arbitrary role'])->assertSessionHasErrors('staff_role');
    }

    public function test_admin_can_date_a_purchase_conversion_in_an_open_historical_year(): void
    {
        $year = FiscalYear::create(['year' => 2024, 'starts_on' => '2024-01-01', 'ends_on' => '2024-12-31', 'status' => 'open']);
        DocumentSeries::create(['document_type' => 'purchase_order', 'name' => 'Historical orders', 'prefix' => 'PO', 'next_number' => 1, 'fiscal_year_id' => $year->id, 'fiscal_year' => 2024, 'is_active' => true]);
        $supplier = Supplier::create(['legal_name' => 'Historical Supplier', 'country' => 'ES', 'is_active' => true]);
        $rfq = PurchaseDocument::create(['type' => 'purchase_rfq', 'supplier_id' => $supplier->id, 'number' => 'HIST-RFQ', 'status' => 'draft', 'issue_date' => today(), 'subtotal' => 0, 'tax_amount' => 0, 'amount' => 0]);
        $this->actingAs(User::factory()->create(['role' => 'commercial']))->post(route('purchases.convert', $rfq), ['issue_date' => '2024-05-10'])->assertSessionHasErrors('issue_date');
        $this->assertSame(0, PurchaseDocument::where('type', 'purchase_order')->count());
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('purchases.convert', $rfq), ['issue_date' => '2024-05-10'])->assertSessionHasNoErrors()->assertRedirect();
        $order = PurchaseDocument::where('type', 'purchase_order')->firstOrFail();
        $this->assertSame('2024-05-10', $order->issue_date->toDateString());
        $this->assertSame('PO-2024-00001', $order->number);
        $this->assertTrue($order->created_at->isToday());
        $this->post(route('purchases.convert', $rfq), ['issue_date' => '2024-05-11'])->assertUnprocessable();
        $this->assertSame('2024-05-10', $order->fresh()->issue_date->toDateString());

        $year->update(['status' => 'closed']);
        $another = $rfq->replicate();
        $another->fill(['number' => 'HIST-RFQ-2', 'status' => 'draft', 'locked_at' => null])->save();
        $this->post(route('purchases.convert', $another), ['issue_date' => '2024-06-01'])->assertSessionHasErrors('issue_date');
        $this->assertSame(1, PurchaseDocument::where('type', 'purchase_order')->count());
    }

    public function test_historical_quote_invoice_uses_selected_date_and_relative_due_date(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $person = Person::create(['category' => 'customer', 'first_name' => 'Historical']);
        $quote = Quote::create(['person_id' => $person->id, 'number' => 'HIST-Q', 'status' => 'accepted', 'issue_date' => '2024-05-01', 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'customer_snapshot' => ['payment_terms_days' => 15]]);
        $quote->lines()->create(['position' => 1, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'total' => 121, 'is_selected' => true]);
        $this->actingAs($user)->post(route('quotes.convert', $quote), ['type' => 'invoice', 'issue_date' => '2024-05-10'])->assertSessionHasNoErrors()->assertRedirect();
        $invoice = SalesDocument::firstOrFail();
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('2024-05-10', $invoice->issue_date->toDateString());
        $this->assertSame('2024-05-25', $invoice->due_date->toDateString());
        $this->assertTrue($invoice->created_at->isToday());
    }

    public function test_only_admin_can_enter_an_overdue_next_action_when_creating_an_opportunity(): void
    {
        $person = Person::create(['category' => 'customer', 'first_name' => 'Historical']);
        $payload = ['person_id' => $person->id, 'title' => 'Historical opportunity', 'stage' => 'qualified', 'probability' => 25, 'estimated_value' => 100, 'source' => 'referral', 'next_action_due_at' => '2024-05-10 10:00'];
        $this->actingAs(User::factory()->create(['role' => 'commercial']))->post(route('opportunities.store'), $payload)->assertSessionHasErrors('next_action_due_at');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('opportunities.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('crm_activities', ['due_at' => '2024-05-10 10:00:00']);
    }

    public function test_derived_sales_invoice_keeps_the_admin_date(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $person = Person::create(['category' => 'customer', 'first_name' => 'Historical']);
        $order = SalesDocument::create(['type' => 'order', 'person_id' => $person->id, 'number' => 'HIST-ORDER', 'status' => 'draft', 'issue_date' => '2024-05-01', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'customer_snapshot' => $person->customerSnapshot()]);
        $line = $order->lines()->create(['position' => 1, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'total' => 121]);
        $payload = ['type' => 'invoice', 'quantities' => [$line->id => 1]];
        $this->post(route('sales-documents.convert', $order), [...$payload, 'issue_date' => 'not-a-date'])->assertSessionHasErrors('issue_date');
        $this->post(route('sales-documents.convert', $order), [...$payload, 'issue_date' => '2024-05-10'])->assertSessionHasNoErrors()->assertRedirect();
        $invoice = SalesDocument::where('type', 'invoice')->firstOrFail();
        $this->assertSame('2024-05-10', $invoice->issue_date->toDateString());
        $this->assertSame('2024-06-09', $invoice->due_date->toDateString());
    }
}
