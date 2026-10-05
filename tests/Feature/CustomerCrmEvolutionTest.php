<?php

namespace Tests\Feature;

use App\Models\CrmTag;
use App\Models\CustomerConsent;
use App\Models\CustomerContact;
use App\Models\DocumentDelivery;
use App\Models\Installation;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SavedCrmFilter;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\Customers\CustomerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCrmEvolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // These contract assertions deliberately exercise the optional English UI.
        $this->withSession(['locale' => 'en']);
        app()->setLocale('en');
    }

    public function test_customer_can_have_multiple_contacts_with_one_primary_contact(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Arevantes', 'company_name' => 'Cliente Empresa SL']);

        $this->actingAs($user)->post(route('people.contacts.store', $customer), [
            'first_name' => 'Ana',
            'position' => 'Gerencia',
            'email' => 'ana@example.test',
            'preferred_channel' => 'email',
            'is_primary' => true,
        ])->assertRedirect();
        $this->post(route('people.contacts.store', $customer), [
            'first_name' => 'Luis',
            'position' => 'Mantenimiento',
            'phone' => '600123123',
            'preferred_channel' => 'phone',
            'is_primary' => true,
        ])->assertRedirect();

        $this->assertSame(2, CustomerContact::where('person_id', $customer->id)->count());
        $this->assertSame('Luis', CustomerContact::where('person_id', $customer->id)->where('is_primary', true)->sole()->first_name);
    }

    public function test_first_contact_is_primary_and_deleting_it_promotes_the_next_contact(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Comunidad Centro']);

        $this->actingAs($user)->post(route('people.contacts.store', $customer), [
            'first_name' => 'Ana',
            'roles' => ['property_manager'],
            'is_primary' => false,
        ])->assertRedirect();
        $this->post(route('people.contacts.store', $customer), [
            'first_name' => 'Luis',
            'roles' => ['technical'],
            'is_primary' => false,
        ])->assertRedirect();

        $ana = CustomerContact::where('person_id', $customer->id)->where('first_name', 'Ana')->sole();
        $this->assertTrue($ana->is_primary);

        $this->delete(route('people.contacts.destroy', [$customer, $ana]))->assertRedirect();

        $this->assertTrue(CustomerContact::where('person_id', $customer->id)->where('first_name', 'Luis')->sole()->is_primary);
    }

    public function test_contact_responsibilities_enforce_billing_and_signatory_requirements(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Empresa Norte']);

        $this->actingAs($user)->post(route('people.contacts.store', $customer), [
            'first_name' => 'Ana',
            'roles' => ['billing'],
            'is_primary' => false,
        ])->assertSessionHasErrors('email');

        $this->post(route('people.contacts.store', $customer), [
            'first_name' => 'Luis',
            'roles' => ['technical'],
            'is_authorized_signatory' => true,
            'document_number' => '12345678Z',
            'is_primary' => false,
        ])->assertSessionHasErrors('is_authorized_signatory');

        $this->post(route('people.contacts.store', $customer), [
            'first_name' => 'Marta',
            'roles' => ['legal_representative', 'billing'],
            'is_authorized_signatory' => true,
            'document_type' => 'nif',
            'document_number' => '87654321X',
            'email' => 'facturacion@example.test',
            'is_primary' => true,
        ])->assertRedirect();

        $contact = CustomerContact::where('person_id', $customer->id)->sole();
        $this->assertSame(['legal_representative', 'billing'], $contact->roles);
        $this->assertTrue($contact->is_authorized_signatory);
    }

    public function test_customer_snapshot_uses_billing_identity_and_responsible_contact(): void
    {
        $customer = Person::create([
            'category' => 'customer',
            'first_name' => 'Ana',
            'company_name' => 'Empresa Operativa SL',
            'tax_id' => 'B11111111',
            'address_line_1' => 'Calle Operativa 1',
            'billing_name' => 'Grupo Fiscal SL',
            'billing_tax_id' => 'B22222222',
            'billing_address_line_1' => 'Calle Fiscal 2',
            'payment_terms_days' => 30,
        ]);
        CustomerContact::create([
            'person_id' => $customer->id,
            'first_name' => 'Marta',
            'roles' => ['billing'],
            'email' => 'facturacion@example.test',
            'is_primary' => true,
        ]);

        $snapshot = $customer->customerSnapshot();

        $this->assertSame('Grupo Fiscal SL', $snapshot['company_name']);
        $this->assertSame('B22222222', $snapshot['tax_id']);
        $this->assertSame('Calle Fiscal 2', $snapshot['address_line_1']);
        $this->assertSame('facturacion@example.test', $snapshot['billing_email']);
        $this->assertSame('Marta', $snapshot['billing_contact']['name']);
        $this->assertSame(30, $snapshot['payment_terms_days']);
    }

    public function test_customer_segmentation_consent_and_saved_filters_are_traceable(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);

        $this->actingAs($user)->post(route('people.tags.store', $customer), [
            'name' => 'Autoconsumo industrial',
            'color' => 'green',
        ])->assertRedirect();
        $this->post(route('people.consents.store', $customer), [
            'channel' => 'email',
            'status' => 'granted',
            'lawful_basis' => 'consent',
            'source' => 'Formulario firmado',
            'captured_at' => now()->toDateTimeString(),
        ])->assertRedirect();
        $this->post(route('crm-filters.store'), [
            'scope' => 'customers',
            'name' => 'Industriales activos',
            'filters' => ['lifecycle_status' => 'active', 'tag' => CrmTag::firstOrFail()->id, 'unsafe' => 'discarded'],
            'is_default' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('crm_tag_person', ['person_id' => $customer->id]);
        $this->assertSame($user->id, CustomerConsent::firstOrFail()->recorded_by);
        $filter = SavedCrmFilter::firstOrFail();
        $this->assertSame(['lifecycle_status' => 'active', 'tag' => CrmTag::firstOrFail()->id], $filter->filters);
        $this->assertTrue($filter->is_default);
    }

    public function test_customer_360_includes_payments_tickets_and_communications(): void
    {
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $installation = Installation::create(['person_id' => $customer->id, 'service_type' => 'solar', 'status' => 'active', 'site_address_line_1' => 'Calle Sol']);
        $quote = Quote::create(['person_id' => $customer->id, 'installation_id' => $installation->id, 'number' => 'PRE-360-EVO', 'status' => 'sent', 'issue_date' => today(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'customer_snapshot' => []]);
        $invoice = SalesDocument::create(['person_id' => $customer->id, 'installation_id' => $installation->id, 'type' => 'invoice', 'number' => 'FAC-360-EVO', 'status' => 'partial', 'issue_date' => today(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 50, 'customer_snapshot' => []]);
        $invoice->payments()->create(['paid_at' => today(), 'amount' => 50, 'method' => 'bank_transfer']);
        ServiceTicket::create(['installation_id' => $installation->id, 'number' => 'SAT-360-EVO', 'kind' => 'incident', 'priority' => 'normal', 'status' => 'open', 'title' => 'Inverter warning']);
        DocumentDelivery::create(['quote_id' => $quote->id, 'recipient_email' => 'ana@example.test', 'token_hash' => hash('sha256', 'delivery'), 'status' => 'sent', 'expires_at' => now()->addWeek(), 'sent_at' => now()]);

        $profile = app(CustomerProfile::class)->build($customer);

        $this->assertSame(50.0, $profile['metrics']['collected']);
        $this->assertSame(1, $profile['metrics']['open_tickets']);
        $this->assertContains('payment', $profile['timeline']->pluck('kind'));
        $this->assertContains('ticket', $profile['timeline']->pluck('kind'));
        $this->assertContains('communication', $profile['timeline']->pluck('kind'));
    }

    public function test_duplicate_customers_can_be_merged_without_losing_crm_history(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $target = Person::create(['category' => 'customer', 'first_name' => 'Ana', 'email' => 'ana@example.test']);
        $duplicate = Person::create(['category' => 'customer', 'first_name' => 'Ana duplicada', 'email' => 'ana@example.test', 'phone' => '600123123']);
        CustomerContact::create(['person_id' => $duplicate->id, 'first_name' => 'Luis', 'is_primary' => true]);
        $tag = CrmTag::create(['name' => 'Industrial', 'color' => 'green']);
        $duplicate->tags()->attach($tag);

        $this->actingAs($user)->post(route('people.merge', $target), ['duplicate_id' => $duplicate->id])->assertRedirect(route('people.show', $target));

        $this->assertSoftDeleted('people', ['id' => $duplicate->id]);
        $this->assertSame('600123123', $target->refresh()->phone);
        $this->assertDatabaseHas('customer_contacts', ['person_id' => $target->id, 'first_name' => 'Luis']);
        $this->assertDatabaseHas('crm_tag_person', ['person_id' => $target->id, 'crm_tag_id' => $tag->id]);
        $this->assertDatabaseHas('crm_activities', ['person_id' => $target->id, 'title' => 'Duplicate customer merged']);
    }
}
