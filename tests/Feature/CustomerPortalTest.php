<?php

namespace Tests\Feature;

use App\Mail\CustomerPortalMail;
use App\Models\CustomerDocumentAcceptance;
use App\Models\CustomerPortalAccess;
use App\Models\CustomerPortalEvent;
use App\Models\CustomerPortalPreference;
use App\Models\CustomerSatisfactionSurvey;
use App\Models\Installation;
use App\Models\Person;
use App\Models\Quote;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_portal_is_tokenized_and_isolated_by_customer(): void
    {
        Mail::fake();
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana', 'email' => 'ana@example.com']);
        $otherCustomer = Person::create(['category' => 'customer', 'first_name' => 'Luis', 'email' => 'luis@example.com']);
        $installation = Installation::create(['person_id' => $customer->id, 'service_type' => 'solar', 'status' => 'completed', 'project_reference' => 'FV-ANA', 'site_address_line_1' => 'Calle Sol 1']);
        $foreignInstallation = Installation::create(['person_id' => $otherCustomer->id, 'service_type' => 'ev_charger', 'status' => 'completed', 'project_reference' => 'VE-LUIS', 'site_address_line_1' => 'Calle Carga 2']);
        $foreignQuote = Quote::create(['person_id' => $otherCustomer->id, 'number' => 'PRE-2026-99999', 'status' => 'sent', 'issue_date' => '2026-07-13', 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121]);

        $this->actingAs($commercial)->post(route('people.portal.invite', $customer), ['recipient_email' => 'ana@example.com'])->assertRedirect();
        $access = CustomerPortalAccess::firstOrFail();
        $token = null;
        Mail::assertSent(CustomerPortalMail::class, function (CustomerPortalMail $mail) use (&$token) {
            $token = basename(parse_url($mail->url, PHP_URL_PATH));

            return $mail->hasTo('ana@example.com');
        });
        $this->assertSame(hash('sha256', $token), $access->token_hash);

        $this->get(route('customer-portal.show', $token))->assertOk()->assertInertia(fn ($page) => $page->component('portal/index')->where('installations.0.project_reference', 'FV-ANA')->has('installations', 1));
        $this->post(route('customer-portal.tickets.store', $token), ['installation_id' => $installation->id, 'title' => 'Low production', 'description' => 'Production appears lower than expected.', 'priority' => 'normal'])->assertRedirect();
        $this->assertDatabaseHas('service_tickets', ['installation_id' => $installation->id, 'title' => 'Low production']);
        $this->post(route('customer-portal.tickets.store', $token), ['installation_id' => $foreignInstallation->id, 'title' => 'Foreign', 'description' => 'Must not be accepted.', 'priority' => 'normal'])->assertForbidden();
        $this->get(route('customer-portal.documents.pdf', [$token, 'quote', $foreignQuote->id]))->assertNotFound();

        $this->actingAs($commercial)->delete(route('people.portal.revoke', [$customer, $access]))->assertRedirect();
        $this->get(route('customer-portal.show', $token))->assertGone();
        $this->assertSame(1, ServiceTicket::count());
    }

    public function test_portal_acceptance_is_versioned_and_preferences_and_surveys_are_audited(): void
    {
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana', 'email' => 'ana@example.com']);
        $otherCustomer = Person::create(['category' => 'customer', 'first_name' => 'Luis', 'email' => 'luis@example.com']);
        $installation = Installation::create(['person_id' => $customer->id, 'service_type' => 'solar', 'status' => 'completed', 'project_reference' => 'FV-ANA', 'site_address_line_1' => 'Calle Sol 1']);
        $quote = Quote::create(['person_id' => $customer->id, 'number' => 'PRE-2026-00001', 'version' => 3, 'status' => 'sent', 'issue_date' => '2026-08-06', 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121]);
        $foreignQuote = Quote::create(['person_id' => $otherCustomer->id, 'number' => 'PRE-2026-00002', 'status' => 'sent', 'issue_date' => '2026-08-06', 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121]);
        $ticket = ServiceTicket::create(['installation_id' => $installation->id, 'number' => 'SAT-2026-00001', 'kind' => 'incident', 'priority' => 'normal', 'status' => 'resolved', 'title' => 'Resolved ticket', 'resolved_at' => now()]);
        $token = 'secure-portal-token';
        CustomerPortalAccess::create(['person_id' => $customer->id, 'recipient_email' => 'ana@example.com', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addWeek()]);

        $this->get(route('customer-portal.show', $token))->assertOk();
        $this->post(route('customer-portal.documents.accept', [$token, 'quote', $foreignQuote]))->assertNotFound();
        $this->post(route('customer-portal.documents.accept', [$token, 'quote', $quote]))->assertRedirect();
        $this->assertSame('accepted', $quote->refresh()->status);
        $acceptance = CustomerDocumentAcceptance::firstOrFail();
        $this->assertStringStartsWith('quote-v3-', $acceptance->document_version);
        $this->assertSame(64, strlen($acceptance->document_hash));

        $this->patch(route('customer-portal.preferences.update', $token), ['email_documents' => false, 'email_tickets' => true, 'email_maintenance' => false])->assertRedirect();
        $this->assertDatabaseHas(CustomerPortalPreference::class, ['person_id' => $customer->id, 'email_documents' => false, 'email_tickets' => true]);

        $survey = ['service_ticket_id' => $ticket->id, 'context' => 'intervention', 'score' => 5, 'comments' => 'Excellent'];
        $this->post(route('customer-portal.surveys.store', $token), $survey)->assertRedirect();
        $this->post(route('customer-portal.surveys.store', $token), $survey)->assertUnprocessable();
        $this->assertDatabaseHas(CustomerSatisfactionSurvey::class, ['person_id' => $customer->id, 'service_ticket_id' => $ticket->id, 'score' => 5]);
        $this->assertSame(5, $ticket->refresh()->satisfaction_rating);
        $this->assertTrue(CustomerPortalEvent::where('person_id', $customer->id)->where('event_type', 'document_accepted')->exists());
        $this->assertTrue(CustomerPortalEvent::where('person_id', $customer->id)->where('event_type', 'preferences_updated')->exists());
        $this->assertTrue(CustomerPortalEvent::where('person_id', $customer->id)->where('event_type', 'survey_submitted')->exists());
    }
}
