<?php

namespace Tests\Feature;

use App\Mail\DocumentSharedMail;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuoteWorkflowSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_quote_pending_approval_cannot_be_sent_by_email(): void
    {
        Mail::fake();
        $commercial = User::factory()->create(['role' => 'commercial']);
        $quote = $this->quote($commercial, ['approval_status' => 'pending']);

        $this->actingAs($commercial)
            ->post(route('quotes.send', $quote), ['recipient_email' => 'client@example.test'])
            ->assertSessionHasErrors('status');

        $this->assertSame('draft', $quote->fresh()->status);
        $this->assertDatabaseCount('document_deliveries', 0);
        Mail::assertNothingSent();
    }

    public function test_terminal_quote_states_cannot_be_reopened_and_every_transition_is_traced(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $opportunity = Opportunity::create([
            'person_id' => $customer->id,
            'created_by' => $commercial->id,
            'owner_user_id' => $commercial->id,
            'code' => 'OPP-2026-90001',
            'title' => 'Instalación solar',
            'stage' => 'proposal',
            'probability' => 60,
            'estimated_value' => 1210,
        ]);
        $quote = $this->quote($commercial, ['person_id' => $customer->id, 'opportunity_id' => $opportunity->id]);

        $this->actingAs($commercial)->patch(route('quotes.status.update', $quote), ['status' => 'sent'])->assertRedirect();
        $this->patch(route('quotes.status.update', $quote), ['status' => 'accepted'])->assertRedirect();
        $this->patch(route('quotes.status.update', $quote), ['status' => 'draft'])->assertSessionHasErrors('status');

        $this->assertSame('accepted', $quote->fresh()->status);
        $this->assertSame('won', $opportunity->fresh()->stage);
        $this->assertDatabaseHas('quote_status_histories', ['quote_id' => $quote->id, 'from_status' => 'draft', 'to_status' => 'sent', 'channel' => 'internal']);
        $this->assertDatabaseHas('quote_status_histories', ['quote_id' => $quote->id, 'from_status' => 'sent', 'to_status' => 'accepted', 'channel' => 'internal']);
    }

    public function test_quote_approval_requires_a_different_authorized_user(): void
    {
        $creator = User::factory()->create(['role' => 'commercial']);
        $otherCommercial = User::factory()->create(['role' => 'commercial']);
        $administrator = User::factory()->create(['role' => 'admin']);
        $quote = $this->quote($creator, ['approval_status' => 'pending']);

        $this->actingAs($otherCommercial)
            ->post(route('quotes.approval.store', $quote), ['decision' => 'approved'])
            ->assertForbidden();

        $this->actingAs($administrator)
            ->post(route('quotes.approval.store', $quote), ['decision' => 'approved'])
            ->assertRedirect();
        $this->assertSame('approved', $quote->fresh()->approval_status);

        $ownQuote = $this->quote($administrator, ['number' => 'PRE-2026-90002', 'approval_status' => 'pending']);
        $this->post(route('quotes.approval.store', $ownQuote), ['decision' => 'approved'])
            ->assertUnprocessable();
    }

    public function test_quotes_with_commercial_traceability_cannot_be_deleted(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $accepted = $this->quote($commercial, ['status' => 'accepted']);

        $this->actingAs($commercial)->delete(route('quotes.destroy', $accepted))->assertUnprocessable();

        $draft = $this->quote($commercial, ['number' => 'PRE-2026-90003']);
        SalesDocument::create([
            'quote_id' => $draft->id,
            'person_id' => $draft->person_id,
            'created_by' => $commercial->id,
            'type' => 'invoice',
            'number' => 'DRAFT-INV-SECURITY',
            'status' => 'draft',
            'issue_date' => today(),
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 21,
            'amount' => 121,
            'customer_snapshot' => [],
        ]);

        $this->delete(route('quotes.destroy', $draft))->assertUnprocessable();
        $this->assertDatabaseHas('quotes', ['id' => $draft->id]);
    }

    public function test_a_customer_who_blocked_commercial_contact_cannot_receive_a_quote(): void
    {
        Mail::fake();
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create([
            'category' => 'customer',
            'first_name' => 'No contactar',
            'email' => 'blocked@example.test',
            'do_not_contact' => true,
        ]);
        $quote = $this->quote($commercial, ['person_id' => $customer->id]);

        $this->actingAs($commercial)
            ->post(route('quotes.send', $quote), ['recipient_email' => $customer->email])
            ->assertUnprocessable();

        Mail::assertNotSent(DocumentSharedMail::class);
    }

    private function quote(User $creator, array $attributes = []): Quote
    {
        $customerId = $attributes['person_id'] ?? Person::create([
            'category' => 'customer',
            'first_name' => 'Cliente',
            'email' => 'client@example.test',
        ])->id;

        return Quote::create([
            'person_id' => $customerId,
            'created_by' => $creator->id,
            'number' => 'PRE-2026-90001',
            'version' => 1,
            'status' => 'draft',
            'approval_status' => 'not_required',
            'issue_date' => today(),
            'subtotal' => 1000,
            'discount_amount' => 0,
            'tax_amount' => 210,
            'amount' => 1210,
            'customer_snapshot' => [],
            ...$attributes,
        ]);
    }
}
