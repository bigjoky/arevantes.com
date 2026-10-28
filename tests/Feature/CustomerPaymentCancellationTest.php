<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Person;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\JournalPoster;
use App\Services\Billing\BillingWorkspace;
use App\Services\Finance\OutstandingBalanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerPaymentCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function receipt(string $amount = '121.00', bool $posted = true): array
    {
        $user = User::factory()->create(['role' => 'admin']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Synthetic']);
        $document = SalesDocument::create(['type' => 'invoice', 'customer_snapshot' => [], 'person_id' => $customer->id, 'number' => 'SYN-'.Str::uuid(), 'status' => 'issued', 'locked_at' => now(), 'issue_date' => '2026-09-20', 'due_date' => '2099-09-30', 'subtotal' => '100.00', 'tax_amount' => '21.00', 'amount' => '121.00', 'paid_amount' => '0.00']);
        $payment = $document->payments()->create(['created_by' => $user->id, 'paid_at' => '2026-09-21', 'amount' => $amount, 'method' => 'cash', 'reference' => 'SYN-RECEIPT', 'notes' => 'Preserve original']);
        if ($posted) {
            app(AccountingSynchronizer::class)->syncCustomerPayment($payment, $user->id);
        }
        app(OutstandingBalanceCalculator::class)->refreshSalesDocument($document);
        $this->actingAs($user);

        return [$document, $payment, $user];
    }

    private function confirmation(SalesDocumentPayment $payment): array
    {
        return ['reason' => 'Incorrect receipt registration', 'operation_key' => (string) Str::uuid(), 'revision' => $payment->cancellationRevision(), 'confirmed' => '1'];
    }

    public function test_posted_full_receipt_is_preserved_and_reversed_once_with_idempotent_replay(): void
    {
        [$document, $payment, $user] = $this->receipt();
        $original = $payment->accountingEntry()->with('lines')->firstOrFail();
        $originalAttributes = $original->getAttributes();
        $data = $this->confirmation($payment);
        $route = route('sales-documents.payments.cancel', [$document, $payment->id]);
        $this->post($route, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($route, $data)->assertRedirect()->assertSessionHasNoErrors();
        $retained = SalesDocumentPayment::withoutGlobalScope('active_customer_payment')->findOrFail($payment->id);
        $this->assertSame('121.00', $retained->amount);
        $this->assertSame('2026-09-21', $retained->paid_at->toDateString());
        $this->assertSame('Preserve original', $retained->notes);
        $this->assertSame($user->id, $retained->voided_by);
        $this->assertNotNull($retained->voided_at);
        $this->assertSame($originalAttributes, $original->fresh()->getAttributes());
        $this->assertSame($payment->id, $original->fresh()->source->id);
        $this->assertSame('0.00', $document->fresh()->paid_amount);
        $this->assertSame('issued', $document->fresh()->status);
        $reversal = $retained->reversalEntry;
        $this->assertSame($original->id, $reversal->reverses_entry_id);
        foreach ($original->lines as $line) {
            $opposite = $reversal->lines->firstWhere('ledger_account_id', $line->ledger_account_id);
            $this->assertSame($line->debit, $opposite->credit);
            $this->assertSame($line->credit, $opposite->debit);
        }
        $this->assertSame(1, AuditLog::where('event', 'customer_receipt_voided')->count());
        $this->assertSame(1, JournalEntry::where('reverses_entry_id', $original->id)->count());
        $this->assertSame(0, app(BillingWorkspace::class)->payments(true, false)->count());
        $this->get(route('sales-documents.show', $document))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canCancelReceipts', true)->has('detail.payments', 0)->has('voidedPayments', 1)->where('voidedPayments.0.void_reason', $data['reason']));
        $this->post($route, [...$data, 'operation_key' => (string) Str::uuid()])->assertSessionHasErrors('reason');
    }

    public function test_partial_and_multiple_receipts_recalculate_only_active_receipts(): void
    {
        [$document, $first] = $this->receipt('40.00');
        $other = $document->payments()->create(['paid_at' => '2026-09-22', 'amount' => '81.00', 'method' => 'card']);
        app(AccountingSynchronizer::class)->syncCustomerPayment($other);
        $this->post(route('sales-documents.payments.cancel', [$document, $first->id]), $this->confirmation($first))->assertSessionHasNoErrors();
        $this->assertSame('81.00', $document->fresh()->paid_amount);
        $this->assertSame('partial', $document->fresh()->status);
        $this->assertSame('40.00', app(OutstandingBalanceCalculator::class)->receivableDecimal($document->fresh()));
    }

    public function test_unposted_receipt_can_be_cancelled_without_creating_a_journal_entry(): void
    {
        [$document, $payment] = $this->receipt('40.00', false);
        $this->post(route('sales-documents.payments.cancel', [$document, $payment->id]), $this->confirmation($payment))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseHas('sales_document_payments', ['id' => $payment->id, 'reversal_entry_id' => null]);
        $this->assertSame('0.00', $document->fresh()->paid_amount);
    }

    public function test_explicit_confirmation_reason_revision_and_permissions_are_required(): void
    {
        [$document, $payment] = $this->receipt();
        $route = route('sales-documents.payments.cancel', [$document, $payment->id]);
        $this->post($route, [])->assertSessionHasErrors(['reason', 'revision', 'operation_key', 'confirmed']);
        $this->post($route, [...$this->confirmation($payment), 'revision' => str_repeat('0', 64)])->assertSessionHasErrors('reason');
        $this->actingAs(User::factory()->create(['role' => 'technician']))->post($route, $this->confirmation($payment))->assertForbidden();
        $this->assertSame('121.00', $document->fresh()->paid_amount);
    }

    public function test_reconciliation_and_bank_origin_block_cancellation_without_bank_changes(): void
    {
        [$document, $payment, $user] = $this->receipt();
        $bank = BankAccount::create(['name' => 'SYN Bank', 'currency' => 'EUR', 'opening_balance' => 0]);
        $transaction = $bank->transactions()->create(['booked_on' => '2026-09-21', 'amount' => 121, 'currency' => 'EUR', 'description' => 'Synthetic', 'import_hash' => hash('sha256', 'SYN-RECEIPT'), 'status' => 'reconciled']);
        $reconciliation = BankReconciliation::create(['bank_transaction_id' => $transaction->id, 'sales_document_payment_id' => $payment->id, 'reconciled_by' => $user->id, 'reconciled_at' => now(), 'method' => 'manual']);
        $before = $transaction->fresh()->getAttributes();
        $this->post(route('sales-documents.payments.cancel', [$document, $payment->id]), $this->confirmation($payment))->assertSessionHasErrors('reason');
        $this->assertSame($before, $transaction->fresh()->getAttributes());
        $this->assertDatabaseHas('bank_reconciliations', ['id' => $reconciliation->id]);
        $reconciliation->delete(); // Synthetic fixture: exercise origin-only protection too.
        $payment->update(['origin_bank_transaction_id' => $transaction->id]);
        $this->post(route('sales-documents.payments.cancel', [$document, $payment->id]), $this->confirmation($payment))->assertSessionHasErrors('reason');
    }

    public function test_closed_year_closed_period_and_protected_tax_return_block_cancellation(): void
    {
        [$document, $payment] = $this->receipt();
        $url = route('sales-documents.payments.cancel', [$document, $payment->id]);
        $year = FiscalYear::where('year', 2026)->firstOrFail();
        $year->update(['status' => 'closed']);
        $this->post($url, $this->confirmation($payment))->assertSessionHasErrors('reason');
        $year->update(['status' => 'open']);
        $period = AccountingPeriod::create(['fiscal_year_id' => $year->id, 'year' => 2026, 'month' => 9, 'status' => 'closed']);
        $this->post($url, $this->confirmation($payment))->assertSessionHasErrors('accounting_period');
        $period->update(['status' => 'open']);
        TaxReturn::create(['year' => 2026, 'quarter' => 3, 'prepared_at' => now(), 'output_vat' => 0, 'input_vat' => 0, 'result' => 0, 'snapshot' => [], 'period_from' => '2026-07-01', 'period_to' => '2026-09-30', 'status' => 'prepared']);
        $this->post($url, $this->confirmation($payment))->assertSessionHasErrors('reason');
        $this->assertSame(0, JournalEntry::where('event', 'payment_voided')->count());
    }

    public function test_credit_note_is_preserved_and_used_to_recompute_the_remaining_balance(): void
    {
        [$document, $payment] = $this->receipt();
        $credit = SalesDocument::create(['type' => 'credit_note', 'customer_snapshot' => [], 'source_document_id' => $document->id, 'number' => 'SYN-CREDIT', 'status' => 'issued', 'locked_at' => now(), 'issue_date' => '2026-09-22', 'amount' => '-21.00', 'subtotal' => '-21.00', 'tax_amount' => 0]);
        $this->post(route('sales-documents.payments.cancel', [$document, $payment->id]), $this->confirmation($payment))->assertSessionHasNoErrors();
        $this->assertSame('100.00', app(OutstandingBalanceCalculator::class)->receivableDecimal($document->fresh()));
        $this->assertSame('-21.00', $credit->fresh()->amount);
    }

    public function test_failure_during_reversal_rolls_back_every_effect(): void
    {
        [$document, $payment] = $this->receipt();
        $count = JournalEntry::count();
        $this->mock(JournalPoster::class)->shouldReceive('post')->once()->andThrow(new \RuntimeException('Synthetic posting failure'));
        $this->post(route('sales-documents.payments.cancel', [$document, $payment->id]), $this->confirmation($payment))->assertStatus(500);
        $this->assertNull($payment->fresh()->voided_at);
        $this->assertSame('121.00', $document->fresh()->paid_amount);
        $this->assertSame($count, JournalEntry::count());
        $this->assertSame(0, AuditLog::where('event', 'customer_receipt_voided')->count());
    }

    public function test_receipt_and_allocation_entries_are_both_reversed_for_an_issued_advance(): void
    {
        [$document, $payment, $user] = $this->receipt('40.00', false);
        $document->update(['locked_at' => null, 'collect_before_invoice' => true]);
        app(AccountingSynchronizer::class)->syncCustomerPayment($payment, $user->id);
        $document->update(['locked_at' => now()]);
        app(AccountingSynchronizer::class)->allocateCollectedPayments($document->fresh(), $user->id);
        $this->post(route('sales-documents.payments.cancel', [$document, $payment->id]), $this->confirmation($payment))->assertSessionHasNoErrors();
        $this->assertSame(2, JournalEntry::where('entry_kind', 'reversal')->count());
        $this->assertSame('0.00', $document->fresh()->paid_amount);
    }

    public function test_cancelled_unissued_milestone_retains_history_and_cannot_be_deleted(): void
    {
        [$document, $payment] = $this->receipt('40.00', false);
        $document->update(['locked_at' => null, 'collect_before_invoice' => true, 'status' => 'draft']);
        $this->post(route('sales-documents.payments.cancel', [$document, $payment->id]), $this->confirmation($payment))->assertSessionHasNoErrors();
        $this->delete(route('sales-documents.destroy', $document))->assertStatus(422);
        $this->assertDatabaseHas('sales_document_payments', ['id' => $payment->id]);
        $this->assertSame('draft', $document->fresh()->status);
    }

    public function test_failed_allocation_reversal_rolls_back_the_first_reversal_too(): void
    {
        [$document, $payment, $user] = $this->receipt('40.00', false);
        $document->update(['locked_at' => null, 'collect_before_invoice' => true]);
        app(AccountingSynchronizer::class)->syncCustomerPayment($payment, $user->id);
        $document->update(['locked_at' => now()]);
        app(AccountingSynchronizer::class)->allocateCollectedPayments($document->fresh(), $user->id);
        $count = JournalEntry::count();
        Event::listen('eloquent.created: '.JournalEntry::class, function (JournalEntry $entry): void {
            if ($entry->event === 'allocation_voided') {
                throw new \RuntimeException('Synthetic failure in second reversal');
            }
        });
        try {
            $this->post(route('sales-documents.payments.cancel', [$document, $payment->id]), $this->confirmation($payment))->assertStatus(500);
        } finally {
            Event::forget('eloquent.created: '.JournalEntry::class);
        }
        $this->assertSame($count, JournalEntry::count());
        $this->assertNull($payment->fresh()->voided_at);
        $this->assertSame(0, AuditLog::where('event', 'customer_receipt_voided')->count());
    }

    public function test_replaying_original_creation_does_not_recreate_a_cancelled_receipt(): void
    {
        [$document, $payment] = $this->receipt();
        $key = (string) Str::uuid();
        $payment->update(['idempotency_key' => $key]);
        $this->post(route('sales-documents.payments.cancel', [$document, $payment->id]), $this->confirmation($payment))->assertSessionHasNoErrors();
        $this->post(route('sales-documents.payments.store', $document), ['idempotency_key' => $key, 'paid_at' => '2026-09-21', 'amount' => '121.00', 'method' => 'cash', 'reference' => 'SYN-RECEIPT', 'notes' => 'Preserve original'])->assertSessionHasErrors('amount');
        $this->assertSame(1, SalesDocumentPayment::withoutGlobalScope('active_customer_payment')->count());
        $this->assertSame('0.00', $document->fresh()->paid_amount);
    }
}
