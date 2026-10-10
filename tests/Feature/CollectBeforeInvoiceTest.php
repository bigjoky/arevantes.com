<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CollectBeforeInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): array
    {
        Mail::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Cobro primero']);
        $quote = Quote::create(['person_id' => $customer->id, 'created_by' => auth()->id(), 'number' => 'PRE-COLLECT', 'status' => 'draft', 'approval_status' => 'approved', 'acceptance_payment_percentage' => 50, 'issue_date' => today(), 'subtotal' => 1000, 'tax_amount' => 210, 'amount' => 1210]);
        $quote->lines()->create(['position' => 1, 'description' => 'Instalación', 'quantity' => 1, 'unit_price' => 1000, 'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 1000, 'tax_amount' => 210, 'total' => 1210]);
        $this->patch(route('quotes.status.update', $quote), ['status' => 'accepted'])->assertSessionHasNoErrors();

        return [$quote->fresh(), $quote->acceptanceInvoice()->firstOrFail()];
    }

    public function test_collecting_does_not_issue_and_manual_issue_allocates_advances_once(): void
    {
        [$quote, $milestone] = $this->plan();
        $this->assertSame('draft', $milestone->status);
        $this->assertNull($milestone->document_series_id);
        $this->assertNull($milestone->issued_at);
        $this->assertSame(0, JournalEntry::count());
        $this->post(route('sales-documents.issue', $milestone), ['issue_date' => today()->toDateString()])->assertSessionHasErrors('issue_date');
        foreach ([200, 405] as $amount) {
            $this->post(route('sales-documents.payments.store', $milestone), ['paid_at' => today()->toDateString(), 'amount' => $amount, 'method' => 'bank_transfer'])->assertSessionHasNoErrors();
        }
        $milestone->refresh();
        $this->assertSame('draft', $milestone->status);
        $this->assertNull($milestone->locked_at);
        $this->assertSame('605.00', $milestone->paid_amount);
        $this->assertSame('cleared', $quote->fresh()->financial_status);
        $this->assertEquals(-605, $this->balance('438'));
        $this->assertEquals(0, $this->balance('430'));
        $this->assertEquals(0, $this->balance('700'));
        Mail::assertNothingSent();
        $this->post(route('sales-documents.issue', $milestone), ['issue_date' => today()->toDateString()])->assertSessionHasNoErrors();
        $milestone->refresh();
        $this->assertSame('paid', $milestone->status);
        $this->assertNotNull($milestone->issued_at);
        $this->assertNotNull($milestone->accountingEntry);
        $this->assertFalse(str_starts_with($milestone->number, 'DRAFT'));
        $this->assertEquals(0, $this->balance('438'));
        $this->assertEquals(0, $this->balance('430'));
        $this->assertEquals(-500, $this->balance('700'));
        $this->assertEquals(605, $this->balance('572'));
        $entries = JournalEntry::count();
        $this->post(route('sales-documents.issue', $milestone), ['issue_date' => today()->toDateString()])->assertStatus(422);
        $this->assertSame($entries, JournalEntry::count());
        $this->put(route('sales-documents.milestone', $milestone), ['billing_amount' => 100, 'billing_label' => 'Changed'])->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_amount_edits_preserve_receipts_and_bound_remaining_allocation(): void
    {
        [$quote, $milestone] = $this->plan();
        $this->put(route('sales-documents.milestone', $milestone), ['billing_amount' => 300.50, 'billing_label' => 'Reserva'])->assertSessionHasNoErrors();
        $this->assertSame('300.50', $milestone->fresh()->amount);
        $this->assertDatabaseHas('audit_logs', ['event' => 'billing_milestone_updated']);
        $this->post(route('sales-documents.payments.store', $milestone), ['paid_at' => today()->toDateString(), 'amount' => 300.50, 'method' => 'cash'])->assertSessionHasNoErrors();
        $this->put(route('sales-documents.milestone', $milestone), ['billing_amount' => 300, 'billing_label' => 'Too little'])->assertSessionHasErrors('billing_amount');
        $this->delete(route('sales-documents.destroy', $milestone))->assertStatus(422);
        $this->put(route('sales-documents.milestone', $milestone), ['billing_amount' => 400, 'billing_label' => 'Reserva ampliada'])->assertSessionHasNoErrors();
        $this->assertSame('partial', $quote->fresh()->financial_status);
        $this->assertSame('300.50', $milestone->fresh()->paid_amount);
        $this->post(route('quotes.convert', $quote), ['type' => 'invoice', 'billing_amount' => 810])->assertRedirect()->assertSessionHasNoErrors();
        $final = SalesDocument::where('quote_id', $quote->id)->whereKeyNot($milestone->id)->firstOrFail();
        $this->assertTrue($final->collect_before_invoice);
        $this->assertSame('draft', $final->status);
        $this->put(route('sales-documents.milestone', $milestone), ['billing_amount' => 400.01, 'billing_label' => 'Too much'])->assertSessionHasErrors('billing_amount');
        $this->post(route('sales-documents.payments.store', $milestone), ['paid_at' => today()->toDateString(), 'amount' => 100, 'method' => 'cash'])->assertSessionHasErrors('amount');
        $this->assertSame(1, $milestone->payments()->count());
    }

    private function balance(string $account): float
    {
        return (float) JournalLine::whereHas('account', fn ($q) => $q->where('code', $account))->selectRaw('coalesce(sum(debit-credit),0) as balance')->value('balance');
    }
}
