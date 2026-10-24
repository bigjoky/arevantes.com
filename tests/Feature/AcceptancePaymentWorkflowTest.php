<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Installation;
use App\Models\JournalLine;
use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\SalesDocument;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Documents\QuoteBillingPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AcceptancePaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_small_and_vat_exempt_installments_preserve_the_target_without_charging_free_lines(): void
    {
        foreach ([0, 21] as $taxRate) {
            $quote = new Quote(['amount' => '0.03']);
            $quote->setRelation('lines', collect([
                new QuoteLine(['position' => 1, 'is_selected' => true, 'quantity' => 1, 'unit_price' => '0.03', 'subtotal' => '0.03', 'discount_amount' => 0, 'discount_percent' => 0, 'tax_percent' => $taxRate, 'tax_amount' => 0, 'total' => '0.03']),
                new QuoteLine(['position' => 2, 'is_selected' => true, 'quantity' => 1, 'unit_price' => 1, 'subtotal' => 1, 'discount_amount' => 1, 'discount_percent' => 100, 'tax_percent' => $taxRate, 'tax_amount' => 0, 'total' => 0]),
            ]));
            $result = app(QuoteBillingPlan::class)->installmentForAmount($quote, 0.01, false);
            $this->assertEquals(0.01, $result['totals']['amount']);
            $this->assertEquals(0, $result['lines'][1]['total']);
            $this->assertEquals(0, $result['lines'][1]['tax_amount']);
            if ($taxRate === 0) {
                $this->assertEquals(0, $result['totals']['tax_amount']);
            }
        }
    }

    public function test_paper_acceptance_with_discounted_and_free_lines_balances_advance_and_final_invoices(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'admin']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Cliente sin email']);
        $quote = Quote::create([
            'person_id' => $customer->id, 'created_by' => $user->id, 'number' => 'PRE-PAPER',
            'status' => 'draft', 'approval_status' => 'approved', 'acceptance_payment_percentage' => 50,
            'acceptance_payment_due_days' => 5, 'issue_date' => today(), 'subtotal' => 5867.77,
            'discount_amount' => 3140.50, 'tax_amount' => 572.73, 'amount' => 3300,
        ]);
        foreach ([
            [4049.59, 50, 2024.80, 425.21, 2450],
            [1404.96, 50, 702.48, 147.52, 850],
            [247.93, 100, 247.93, 0, 0],
            [165.29, 100, 165.29, 0, 0],
        ] as $position => [$subtotal, $discountPercent, $discount, $tax, $total]) {
            $quote->lines()->create([
                'position' => $position + 1, 'description' => 'Partida '.($position + 1),
                'quantity' => 1, 'unit_price' => $subtotal, 'discount_percent' => $discountPercent,
                'tax_percent' => 21, 'subtotal' => $subtotal, 'discount_amount' => $discount,
                'tax_amount' => $tax, 'total' => $total,
            ]);
        }
        $this->actingAs($user)->patch(route('quotes.status.update', $quote), ['status' => 'accepted'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('accepted', $quote->fresh()->status);
        $advance = $quote->acceptanceInvoice()->firstOrFail();
        $this->assertSame('1650.00', $advance->amount);
        $this->assertNull($advance->accountingEntry);
        foreach ($advance->lines as $line) {
            $this->assertEqualsWithDelta((float) $line->total, (float) $line->subtotal - (float) $line->discount_amount + (float) $line->tax_amount, 0.000001);
            if ((float) $line->discount_percent === 100.0) {
                $this->assertSame('0.00', $line->total);
                $this->assertSame('0.00', $line->tax_amount);
            }
        }
        Mail::assertNothingSent();
        $final = app(QuoteBillingPlan::class)->installment($quote->fresh(), 50, true);
        $this->assertEquals(1650, $final['totals']['amount']);
        foreach ($final['lines'] as $line) {
            $this->assertEqualsWithDelta($line['total'], $line['subtotal'] - $line['discount_amount'] + $line['tax_amount'], 0.000001);
        }
        $this->assertEqualsWithDelta(572.73, (float) $advance->tax_amount + $final['totals']['tax_amount'], 0.000001);
    }

    public function test_acceptance_plans_advance_without_invoicing_and_bank_reconciliation_clears_operations(): void
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
        $this->assertSame('draft', $invoice->status);
        $this->assertTrue($invoice->collect_before_invoice);
        $this->assertNull($invoice->locked_at);
        $this->assertNull($invoice->issued_at);
        $this->assertNull($invoice->issuer_snapshot);
        $this->assertSame('30.00', $invoice->billing_percentage);
        $this->assertSame('363.00', $invoice->amount);
        $this->assertSame(today()->addDays(5)->toDateString(), $invoice->due_date->toDateString());
        $this->assertNull($invoice->accountingEntry);
        $this->assertDatabaseMissing('document_deliveries', ['sales_document_id' => $invoice->id]);
        Mail::assertNothingSent();
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
        ])->assertRedirect()->assertSessionHasNoErrors();

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

        $this->assertSame('draft', $invoice->refresh()->status);
        $this->assertSame('363.00', $invoice->paid_amount);
        $this->assertNull($invoice->accountingEntry);
        $this->assertSame('cleared', $quote->refresh()->financial_status);
        $this->assertNotNull($quote->financially_cleared_at);
        $this->assertSame('cleared', $installation->refresh()->financial_status);
        $this->assertNotNull($installation->financially_cleared_at);
        $this->assertSame('reconciled', $transaction->refresh()->status);
        $this->post(route('sales-documents.issue', $invoice), ['issue_date' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->accountingEntry);

        $this->post(route('work-orders.store'), [
            'installation_id' => $installation->id,
            'title' => 'Install system',
            'kind' => 'installation',
        ])->assertRedirect();

        $executionOrder = WorkOrder::where('kind', 'installation')->firstOrFail();
        $this->delete(route('banking.reconciliations.undo', $transaction->reconciliation))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertEquals(0, (float) JournalLine::whereHas('account', fn ($q) => $q->where('code', '438'))->selectRaw('sum(debit-credit) as balance')->value('balance'));
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
