<?php

namespace Tests\Feature;

use App\Mail\DocumentSharedMail;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Installation;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AcceptancePaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceptance_issues_advance_invoice_and_bank_reconciliation_clears_operations(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana', 'tax_id' => '12345678Z', 'billing_email' => 'ana@example.com']);
        $installation = Installation::create([
            'person_id' => $customer->id,
            'service_type' => 'solar',
            'status' => 'quoted',
            'site_address_line_1' => 'Calle Sol 1',
        ]);
        $quote = Quote::create([
            'person_id' => $customer->id,
            'installation_id' => $installation->id,
            'created_by' => $user->id,
            'number' => 'PRE-2026-00900',
            'status' => 'sent',
            'approval_status' => 'not_required',
            'acceptance_payment_percentage' => 30,
            'acceptance_payment_due_days' => 5,
            'issue_date' => today(),
            'subtotal' => 1000,
            'discount_amount' => 0,
            'tax_amount' => 210,
            'amount' => 1210,
            'customer_snapshot' => $customer->only(['first_name', 'tax_id', 'billing_email']),
        ]);
        $quote->lines()->create([
            'position' => 1,
            'description' => 'Solar installation',
            'quantity' => 1,
            'unit_price' => 1000,
            'discount_percent' => 0,
            'tax_percent' => 21,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'tax_amount' => 210,
            'total' => 1210,
        ]);

        $this->actingAs($user)
            ->patch(route('quotes.status.update', $quote), ['status' => 'accepted'])
            ->assertRedirect();

        $invoice = SalesDocument::where('is_acceptance_invoice', true)->firstOrFail();
        $this->assertSame('issued', $invoice->status);
        $this->assertSame(CompanyProfile::firstOrFail()->documentSnapshot(), $invoice->issuer_snapshot);
        $this->assertSame('30.00', $invoice->billing_percentage);
        $this->assertSame('363.00', $invoice->amount);
        $this->assertSame(today()->addDays(5)->toDateString(), $invoice->due_date->toDateString());
        $this->assertNotNull($invoice->accountingEntry);
        $this->assertDatabaseHas('document_deliveries', ['sales_document_id' => $invoice->id, 'recipient_email' => 'ana@example.com', 'status' => 'sent']);
        Mail::assertSent(DocumentSharedMail::class, fn (DocumentSharedMail $mail) => $mail->document->is($invoice));
        $this->assertSame('pending', $quote->refresh()->financial_status);
        $this->assertSame('pending', $installation->refresh()->financial_status);
        $this->patch(route('quotes.status.update', $quote), ['status' => 'rejected'])
            ->assertSessionHasErrors('status');

        $this->post(route('quotes.convert', $quote), ['type' => 'delivery_note'])
            ->assertRedirect();
        $deliveryNote = SalesDocument::where('type', 'delivery_note')->firstOrFail();
        $this->post(route('sales-documents.dispatch', $deliveryNote))
            ->assertSessionHasErrors('financial_status');

        $this->post(route('work-orders.store'), [
            'installation_id' => $installation->id,
            'title' => 'Install system',
            'kind' => 'installation',
        ])->assertSessionHasErrors('financial_status');

        $this->post(route('work-orders.store'), [
            'installation_id' => $installation->id,
            'title' => 'Technical survey',
            'kind' => 'survey',
        ])->assertRedirect();

        $account = BankAccount::create(['name' => 'Collections', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $transaction = $account->transactions()->create([
            'booked_on' => today(),
            'amount' => 363,
            'currency' => 'EUR',
            'description' => 'Acceptance payment '.$invoice->number,
            'reference' => $invoice->number,
            'import_hash' => hash('sha256', 'acceptance-payment'),
            'status' => 'pending',
        ]);

        $this->post(route('banking.auto', $account))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame('cleared', $quote->refresh()->financial_status);
        $this->assertNotNull($quote->financially_cleared_at);
        $this->assertSame('cleared', $installation->refresh()->financial_status);
        $this->assertNotNull($installation->financially_cleared_at);
        $this->assertSame('reconciled', $transaction->refresh()->status);

        $this->post(route('work-orders.store'), [
            'installation_id' => $installation->id,
            'title' => 'Install system',
            'kind' => 'installation',
        ])->assertRedirect();

        $executionOrder = WorkOrder::where('kind', 'installation')->firstOrFail();
        $this->delete(route('banking.reconciliations.undo', $transaction->reconciliation))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('pending', $quote->refresh()->financial_status);
        $this->assertSame('pending', $installation->refresh()->financial_status);
        $this->patch(route('work-orders.update', $executionOrder), ['status' => 'in_progress'])
            ->assertSessionHasErrors('financial_status');
    }

    public function test_zero_percent_acceptance_clears_without_issuing_an_invoice(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'No advance']);
        $quote = Quote::create([
            'person_id' => $customer->id,
            'created_by' => $user->id,
            'number' => 'PRE-2026-00901',
            'status' => 'sent',
            'approval_status' => 'not_required',
            'acceptance_payment_percentage' => 0,
            'acceptance_payment_due_days' => 0,
            'issue_date' => today(),
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 21,
            'amount' => 121,
            'customer_snapshot' => [],
        ]);

        $this->actingAs($user)
            ->patch(route('quotes.status.update', $quote), ['status' => 'accepted'])
            ->assertRedirect();

        $this->assertSame('cleared', $quote->refresh()->financial_status);
        $this->assertDatabaseCount('sales_documents', 0);
    }
}
