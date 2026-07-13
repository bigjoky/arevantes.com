<?php

namespace Tests\Feature;

use App\Mail\DocumentSharedMail;
use App\Models\DocumentDelivery;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DocumentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_is_sent_with_a_hashed_token_and_can_be_accepted_publicly(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana', 'email' => 'ana@example.com']);
        $installation = Installation::create(['person_id' => $customer->id, 'service_type' => 'solar', 'status' => 'quoted', 'site_address_line_1' => 'Calle Sol 1']);
        $opportunity = Opportunity::create(['person_id' => $customer->id, 'installation_id' => $installation->id, 'code' => 'OPP-2026-00100', 'title' => 'Solar installation', 'stage' => 'proposal', 'probability' => 60, 'estimated_value' => 1000]);
        $quote = Quote::create(['opportunity_id' => $opportunity->id, 'person_id' => $customer->id, 'installation_id' => $installation->id, 'created_by' => $user->id, 'number' => 'PRE-2026-00001', 'status' => 'draft', 'issue_date' => '2026-07-13', 'subtotal' => 1000, 'discount_amount' => 0, 'tax_amount' => 210, 'amount' => 1210, 'customer_snapshot' => ['first_name' => 'Ana', 'email' => 'ana@example.com']]);
        $quote->lines()->create(['position' => 1, 'description' => 'Solar installation', 'quantity' => 1, 'unit_price' => 1000, 'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 1000, 'discount_amount' => 0, 'tax_amount' => 210, 'total' => 1210]);

        $this->actingAs($user)->post(route('quotes.send', $quote), ['recipient_email' => 'ana@example.com'])->assertRedirect();
        $delivery = DocumentDelivery::firstOrFail();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('sent', $quote->refresh()->status);
        $token = null;
        Mail::assertSent(DocumentSharedMail::class, function (DocumentSharedMail $mail) use (&$token) {
            $token = basename(parse_url($mail->url, PHP_URL_PATH));

            return $mail->hasTo('ana@example.com');
        });
        $this->assertNotNull($token);
        $this->assertNotSame($token, $delivery->token_hash);
        $this->assertSame(hash('sha256', $token), $delivery->token_hash);

        $this->get(route('documents.shared.show', $token))->assertOk();
        $this->assertNotNull($delivery->refresh()->first_viewed_at);
        $this->post(route('documents.shared.respond', $token), ['response' => 'accepted'])->assertRedirect(route('documents.shared.show', $token));
        $this->assertSame('accepted', $quote->refresh()->status);
        $this->assertSame('approved', $installation->refresh()->status);
        $this->assertSame('won', $opportunity->refresh()->stage);
        $this->assertSame(100, $opportunity->probability);
        $this->assertSame('accepted', $delivery->refresh()->status);
        $this->post(route('documents.shared.respond', $token), ['response' => 'rejected'])->assertUnprocessable();
    }

    public function test_expired_document_link_is_not_accessible(): void
    {
        $delivery = DocumentDelivery::create(['recipient_email' => 'test@example.com', 'token_hash' => hash('sha256', 'expired-token'), 'status' => 'sent', 'expires_at' => now()->subMinute()]);
        $this->get(route('documents.shared.show', 'expired-token'))->assertGone();
    }
}
