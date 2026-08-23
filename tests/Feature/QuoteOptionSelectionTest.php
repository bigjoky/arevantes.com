<?php

namespace Tests\Feature;

use App\Mail\DocumentSharedMail;
use App\Models\DocumentDelivery;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\User;
use App\Services\Documents\DocumentFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuoteOptionSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_selects_options_and_one_alternative_before_acceptance(): void
    {
        Mail::fake();
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Cliente', 'email' => 'cliente@example.test']);
        $quote = Quote::create([
            'person_id' => $customer->id, 'created_by' => $commercial->id, 'number' => 'PRE-OPT-1', 'version' => 1,
            'status' => 'draft', 'approval_status' => 'not_required', 'issue_date' => today(), 'subtotal' => 1000,
            'discount_amount' => 0, 'tax_amount' => 210, 'amount' => 1210, 'customer_snapshot' => $customer->toArray(),
        ]);
        $included = $this->line($quote, 1, 'Instalación base', 1000, 'included', null, true);
        $optional = $this->line($quote, 2, 'Monitorización avanzada', 500, 'optional', null, false);
        $alternativeA = $this->line($quote, 3, 'Equipo estándar', 200, 'alternative', 'Equipo', false);
        $alternativeB = $this->line($quote, 4, 'Equipo premium', 300, 'alternative', 'Equipo', false);
        $sharedUrl = null;

        $this->actingAs($commercial)->post(route('quotes.send', $quote), ['recipient_email' => $customer->email])->assertRedirect();
        Mail::assertSent(DocumentSharedMail::class, function (DocumentSharedMail $mail) use (&$sharedUrl): bool {
            $sharedUrl = $mail->url;

            return true;
        });

        $token = basename((string) parse_url($sharedUrl, PHP_URL_PATH));
        $delivery = DocumentDelivery::where('quote_id', $quote->id)->firstOrFail();
        $this->post(route('documents.shared.respond', $token), [
            'response' => 'accepted',
            'expected_version' => $delivery->document_version,
            'expected_hash' => $delivery->document_hash,
            'selected_line_ids' => [$optional->id, $alternativeB->id],
        ])->assertRedirect();

        $quote->refresh();
        $this->assertSame('accepted', $quote->status);
        $this->assertSame(2, $quote->version);
        $this->assertSame('1800.00', $quote->subtotal);
        $this->assertSame('2178.00', $quote->amount);
        $this->assertTrue($included->fresh()->is_selected);
        $this->assertTrue($optional->fresh()->is_selected);
        $this->assertFalse($alternativeA->fresh()->is_selected);
        $this->assertTrue($alternativeB->fresh()->is_selected);

        $this->actingAs($commercial)->post(route('quotes.convert', $quote), ['type' => 'invoice'])->assertRedirect();
        $invoice = SalesDocument::where('quote_id', $quote->id)->where('is_acceptance_invoice', false)->firstOrFail();
        $this->assertCount(3, $invoice->lines);
        $this->assertSame('1524.60', $invoice->amount);
        $this->assertSame(2178.0, (float) SalesDocument::where('quote_id', $quote->id)->sum('amount'));
    }

    public function test_failed_acceptance_rolls_back_the_customer_option_selection(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Cliente']);
        $quote = Quote::create([
            'person_id' => $customer->id, 'created_by' => $commercial->id, 'number' => 'PRE-OPT-2', 'version' => 1,
            'status' => 'sent', 'approval_status' => 'pending', 'issue_date' => today(), 'subtotal' => 1000,
            'discount_amount' => 0, 'tax_amount' => 210, 'amount' => 1210, 'customer_snapshot' => $customer->toArray(),
        ]);
        $this->line($quote, 1, 'Instalación base', 1000, 'included', null, true);
        $optional = $this->line($quote, 2, 'Monitorización avanzada', 500, 'optional', null, false);
        $token = 'customer-response-token';
        $delivery = DocumentDelivery::create([
            'quote_id' => $quote->id,
            'sent_by' => $commercial->id,
            'recipient_email' => 'cliente@example.test',
            'token_hash' => hash('sha256', $token),
            'status' => 'sent',
            'expires_at' => now()->addDay(),
            'sent_at' => now(),
        ]);
        $fingerprint = app(DocumentFingerprint::class)->for($quote);
        $delivery->update(['document_version' => $fingerprint['version'], 'document_hash' => $fingerprint['hash']]);

        $this->post(route('documents.shared.respond', $token), [
            'response' => 'accepted',
            'expected_version' => $fingerprint['version'],
            'expected_hash' => $fingerprint['hash'],
            'selected_line_ids' => [$optional->id],
        ])->assertSessionHasErrors('status');

        $this->assertSame('sent', $quote->fresh()->status);
        $this->assertSame(1, $quote->version);
        $this->assertFalse($optional->fresh()->is_selected);
        $this->assertNull($delivery->fresh()->responded_at);
        $this->assertDatabaseMissing('quote_versions', ['quote_id' => $quote->id, 'version' => 2]);
    }

    private function line(Quote $quote, int $position, string $description, float $price, string $mode, ?string $group, bool $selected)
    {
        return $quote->lines()->create([
            'position' => $position, 'selection_mode' => $mode, 'option_group' => $group, 'is_selected' => $selected,
            'description' => $description, 'quantity' => 1, 'unit_price' => $price, 'discount_percent' => 0,
            'tax_percent' => 21, 'subtotal' => $price, 'discount_amount' => 0, 'tax_amount' => $price * .21, 'total' => $price * 1.21,
        ]);
    }
}
