<?php

namespace Tests\Feature;

use App\Mail\DocumentSharedMail;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\User;
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
        $this->post(route('documents.shared.respond', $token), [
            'response' => 'accepted',
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
        $invoice = SalesDocument::where('quote_id', $quote->id)->firstOrFail();
        $this->assertCount(3, $invoice->lines);
        $this->assertSame('2178.00', $invoice->amount);
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
