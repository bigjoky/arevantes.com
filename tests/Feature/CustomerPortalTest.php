<?php

namespace Tests\Feature;

use App\Mail\CustomerPortalMail;
use App\Models\CustomerPortalAccess;
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
}
