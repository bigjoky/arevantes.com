<?php

namespace Tests\Feature;

use App\Http\Controllers\SalesDocumentController;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Models\DocumentDelivery;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\IssuedInvoiceCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IssuedInvoiceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): SalesDocument
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Original']);
        $this->post(route('sales-documents.store'), [
            'type' => 'invoice', 'person_id' => $customer->id, 'issue_date' => '2026-09-01', 'due_date' => '2026-12-01',
            'lines' => [['description' => 'Original', 'quantity' => 1, 'unit_price' => 100, 'discount_percent' => 0, 'tax_percent' => 21]],
        ])->assertSessionHasNoErrors();
        $invoice = SalesDocument::firstOrFail();
        $this->post(route('sales-documents.issue', $invoice), ['issue_date' => '2026-09-01'])->assertSessionHasNoErrors();

        return $invoice->fresh();
    }

    private function payload(SalesDocument $invoice): array
    {
        return [...$invoice->only(['type', 'person_id', 'installation_id', 'notes']),
            'issue_date' => $invoice->issue_date->toDateString(), 'due_date' => $invoice->due_date?->toDateString(),
            'revision' => IssuedInvoiceCorrection::revision($invoice), 'correction_reason' => 'Corregir los datos de la factura',
            'lines' => $invoice->lines()->get()->map->only(['id', 'product_id', 'unit_id', 'description', 'quantity', 'unit_price', 'discount_percent', 'tax_percent'])->all()];
    }

    public function test_correction_archives_current_pdf_and_retains_the_previous_issued_file(): void
    {
        Storage::fake(config('filesystems.private'));
        $invoice = $this->invoice();
        $oldPath = $invoice->archived_pdf_path;
        $oldBytes = Storage::disk(config('filesystems.private'))->get($oldPath);
        $payload = [...$this->payload($invoice), 'notes' => 'Texto corregido para el PDF', 'lines' => [['description' => 'Partida corregida', 'quantity' => 1, 'unit_price' => 150, 'discount_percent' => 0, 'tax_percent' => 21]]];
        $this->put(route('sales-documents.update-issued', $invoice), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertNotSame($oldPath, $invoice->archived_pdf_path);
        $newBytes = $this->get(route('sales-documents.pdf', $invoice))->assertOk()->getContent();
        $this->assertNotSame($oldBytes, $newBytes);
        $this->assertSame(hash('sha256', $newBytes), $invoice->archived_pdf_hash);
        $this->assertSame($oldBytes, Storage::disk(config('filesystems.private'))->get($oldPath));
        CompanyProfile::firstOrFail()->update(['legal_name' => 'Changed after correction']);
        $this->assertSame($newBytes, $this->get(route('sales-documents.pdf', $invoice))->assertOk()->getContent());
    }

    public function test_failed_correction_rolls_back_the_new_pdf_without_deleting_the_original(): void
    {
        Storage::fake(config('filesystems.private'));
        $invoice = $this->invoice();
        $oldHash = $invoice->archived_pdf_hash;
        $files = Storage::disk(config('filesystems.private'))->allFiles();
        AuditLog::creating(function ($log) {
            if ($log->event === 'issued_invoice_corrected') {
                throw new \RuntimeException('Correction audit failed');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->put(route('sales-documents.update-issued', $invoice), [...$this->payload($invoice), 'notes' => 'Will roll back']);
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Correction audit failed', $exception->getMessage());
        }
        $this->assertSame($oldHash, $invoice->fresh()->archived_pdf_hash);
        $this->assertSame($files, Storage::disk(config('filesystems.private'))->allFiles());
    }

    public function test_admin_can_edit_all_fields_and_repeat_corrections_without_rewriting_original_entry(): void
    {
        $invoice = $this->invoice();
        $original = $invoice->accountingEntry->load('lines')->toArray();
        $identity = array_intersect_key($invoice->attributesToArray(), array_flip(['number', 'document_series_id', 'issued_at', 'locked_at', 'issuer_snapshot']));
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Revisado']);
        DocumentDelivery::create(['sales_document_id' => $invoice->id, 'sent_by' => auth()->id(), 'recipient_email' => 'test@example.test', 'token_hash' => hash('sha256', 'old-link'), 'status' => 'sent', 'expires_at' => now()->addDay()]);
        $this->get(route('sales-documents.edit-issued', $invoice))->assertOk()->assertInertia(fn (Assert $page) => $page->component('sales/form')->where('issuedCorrection', true));
        $payload = [...$this->payload($invoice), 'person_id' => $customer->id, 'issue_date' => '2026-10-01', 'due_date' => '2026-12-10', 'notes' => 'Texto revisado',
            'number' => 'UNAUTHORIZED-NUMBER', 'locked_at' => null,
            'lines' => [['description' => 'Concepto revisado', 'quantity' => 2, 'unit_price' => 100, 'discount_percent' => 10, 'tax_percent' => 10]]];
        $this->put(route('sales-documents.update-issued', $invoice), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $invoice->refresh();
        $this->assertSame('198.00', $invoice->amount);
        $this->assertSame('18.00', $invoice->tax_amount);
        $this->assertSame($customer->id, $invoice->person_id);
        $this->assertSame('Revisado', $invoice->customer_snapshot['first_name']);
        $this->assertSame('2026-10-01', $invoice->issue_date->toDateString());
        $this->assertSame('2026-12-10', $invoice->due_date->toDateString());
        $this->assertSame('Texto revisado', $invoice->notes);
        $this->assertSame($identity, array_intersect_key($invoice->attributesToArray(), $identity));
        $this->assertSame($original, $invoice->accountingEntry->fresh()->load('lines')->toArray());
        $this->assertSame(3, JournalEntry::count());
        $this->assertNotNull($invoice->deliveries()->first()->revoked_at);
        $audit = AuditLog::where('event', 'issued_invoice_corrected')->firstOrFail();
        $this->assertSame('121.00', $audit->old_values['amount']);
        $this->assertSame('Original', $audit->old_values['lines'][0]['description']);
        $this->assertSame('Concepto revisado', $audit->new_values['lines'][0]['description']);
        $payload = $this->payload($invoice);
        $payload['lines'][0]['unit_price'] = 200;
        $this->put(route('sales-documents.update-issued', $invoice), $payload)->assertSessionHasNoErrors();
        $this->assertSame('396.00', $invoice->fresh()->amount);
        $this->assertSame(5, JournalEntry::count());
        $this->assertSame(2, AuditLog::where('event', 'issued_invoice_corrected')->count());
        $this->get(route('sales-documents.show', $invoice))->assertInertia(fn (Assert $page) => $page->where('document.can_correct_issued', true)->has('corrections', 2));
    }

    public function test_paid_invoice_can_increase_and_change_customer_while_receipts_are_reclassified(): void
    {
        $invoice = $this->invoice();
        $this->post(route('sales-documents.payments.store', $invoice), ['paid_at' => '2026-09-15', 'amount' => 121, 'method' => 'bank_transfer'])->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $payment = $invoice->payments()->firstOrFail();
        $oldAccount = $invoice->customer->receivable_account_code ?: '430';
        $originalPayment = $payment->accountingEntry->load('lines')->toArray();
        LedgerAccount::create(['code' => '430001', 'name' => 'Cliente revisado', 'type' => 'asset', 'level' => 6, 'is_postable' => true, 'is_active' => true]);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Nuevo', 'receivable_account_code' => '430001']);
        $payload = [...$this->payload($invoice), 'person_id' => $customer->id];
        $payload['lines'][0]['unit_price'] = 200;
        $this->put(route('sales-documents.update-issued', $invoice), $payload)->assertSessionHasNoErrors();
        $this->assertSame('242.00', $invoice->fresh()->amount);
        $this->assertSame('121.00', $invoice->fresh()->paid_amount);
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame($originalPayment, $payment->accountingEntry->fresh()->load('lines')->toArray());
        $net = [];
        foreach (JournalEntry::with('lines.account')->get() as $entry) {
            foreach ($entry->lines as $line) {
                $net[$line->account->code] = ($net[$line->account->code] ?? 0) + (int) round(((float) $line->debit - (float) $line->credit) * 100);
            }
        }
        $this->assertSame(0, $net[$oldAccount]);
        $this->assertSame(12100, $net['430001']);
        $this->assertSame(12100, $net['572']);
    }

    public function test_registered_payments_and_stale_revisions_prevent_lost_updates(): void
    {
        $invoice = $this->invoice();
        $stale = $this->payload($invoice);
        $this->post(route('sales-documents.payments.store', $invoice), ['paid_at' => '2026-09-15', 'amount' => 100, 'method' => 'cash'])->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->put(route('sales-documents.update-issued', $invoice), $stale)->assertSessionHasErrors('correction_reason');
        $payload = $this->payload($invoice);
        $payload['lines'][0]['unit_price'] = 10;
        $before = IssuedInvoiceCorrection::snapshot($invoice);
        $this->put(route('sales-documents.update-issued', $invoice), $payload)->assertSessionHasErrors('correction_reason');
        $this->assertSame($before, IssuedInvoiceCorrection::snapshot($invoice->fresh()));
        $this->assertSame(0, AuditLog::where('event', 'issued_invoice_corrected')->count());
    }

    public function test_commercial_users_cannot_edit_an_issued_invoice(): void
    {
        $invoice = $this->invoice();
        $payload = $this->payload($invoice);
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        $this->get(route('sales-documents.edit-issued', $invoice))->assertForbidden();
        $this->put(route('sales-documents.update-issued', $invoice), $payload)->assertForbidden();
        $this->get(route('sales-documents.show', $invoice))->assertInertia(fn (Assert $page) => $page->where('document.can_correct_issued', false)->has('corrections', 0));
    }

    public function test_closed_periods_and_prepared_tax_returns_preserve_the_invoice(): void
    {
        $invoice = $this->invoice();
        $before = IssuedInvoiceCorrection::snapshot($invoice);
        $period = AccountingPeriod::create(['fiscal_year_id' => FiscalYear::where('year', 2026)->firstOrFail()->id, 'year' => 2026, 'month' => 9, 'status' => 'open']);
        $period->update(['status' => 'closed']);
        $this->put(route('sales-documents.update-issued', $invoice), $this->payload($invoice))->assertSessionHasErrors('accounting_period');
        $this->assertSame($before, IssuedInvoiceCorrection::snapshot($invoice->fresh()));
        $period->update(['status' => 'open']);
        TaxReturn::create(['tax_type' => 'vat', 'year' => 2026, 'quarter' => 3, 'period_from' => '2026-07-01', 'period_to' => '2026-09-30', 'status' => 'prepared', 'output_vat' => 0, 'input_vat' => 0, 'result' => 0, 'snapshot' => [], 'prepared_at' => now()]);
        $this->put(route('sales-documents.update-issued', $invoice), $this->payload($invoice))->assertSessionHasErrors('correction_reason');
        $this->assertSame($before, IssuedInvoiceCorrection::snapshot($invoice->fresh()));
    }

    public function test_acceptance_invoice_changes_refresh_clearance_and_cannot_overbill_the_proposal(): void
    {
        $invoice = $this->invoice();
        $quote = Quote::create(['person_id' => $invoice->person_id, 'created_by' => auth()->id(), 'number' => 'PRE-COR', 'status' => 'accepted', 'approval_status' => 'approved', 'acceptance_payment_percentage' => 50, 'amount' => 300, 'subtotal' => 300, 'tax_amount' => 0]);
        $invoice->update(['quote_id' => $quote->id, 'is_acceptance_invoice' => true]);
        $this->post(route('sales-documents.payments.store', $invoice), ['paid_at' => '2026-09-15', 'amount' => 121, 'method' => 'cash'])->assertSessionHasNoErrors();
        $invoice->refresh();
        $payload = $this->payload($invoice);
        $payload['lines'][0]['unit_price'] = 200;
        $this->put(route('sales-documents.update-issued', $invoice), $payload)->assertSessionHasNoErrors();
        $this->assertSame('partial', $quote->fresh()->financial_status);
        $this->assertSame('80.67', $invoice->fresh()->billing_percentage);
        $payload = $this->payload($invoice->fresh());
        $payload['lines'][0]['unit_price'] = 400;
        $this->put(route('sales-documents.update-issued', $invoice), $payload)->assertSessionHasErrors('correction_reason');
        $this->assertSame('242.00', $invoice->fresh()->amount);
    }

    public function test_text_changes_preserve_existing_rounding_and_line_identity(): void
    {
        $invoice = $this->invoice();
        $line = $invoice->lines()->firstOrFail();
        $line->update(['quantity' => 3, 'unit_price' => '33.33']);
        $payload = $this->payload($invoice->fresh());
        $payload['lines'][0]['description'] = 'Texto corregido';
        $this->put(route('sales-documents.update-issued', $invoice), $payload)->assertSessionHasNoErrors();
        $this->assertSame('121.00', $invoice->fresh()->amount);
        $this->assertSame('100.00', $line->fresh()->subtotal);
        $this->assertSame('Texto corregido', $line->fresh()->description);
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_source_line_links_survive_edits_and_cannot_exceed_the_order_quantity(): void
    {
        $invoice = $this->invoice();
        $source = SalesDocument::create(['type' => 'order', 'person_id' => $invoice->person_id, 'created_by' => auth()->id(), 'number' => 'PED-COR', 'customer_snapshot' => [], 'status' => 'draft', 'issue_date' => '2026-09-01', 'subtotal' => 200, 'tax_amount' => 42, 'amount' => 242]);
        $sourceLine = $source->lines()->create(['position' => 1, 'description' => 'Origen', 'quantity' => 2, 'unit_price' => 100, 'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 200, 'discount_amount' => 0, 'tax_amount' => 42, 'total' => 242]);
        $invoice->update(['source_document_id' => $source->id]);
        $line = $invoice->lines()->firstOrFail();
        $line->update(['source_line_id' => $sourceLine->id]);
        $payload = $this->payload($invoice->fresh());
        $payload['lines'][0]['quantity'] = 2;
        $this->put(route('sales-documents.update-issued', $invoice), $payload)->assertSessionHasNoErrors();
        $this->assertSame($sourceLine->id, $line->fresh()->source_line_id);
        $this->assertSame('complete', $source->fresh()->billing_status);
        $payload = $this->payload($invoice->fresh());
        $payload['lines'][0]['quantity'] = 3;
        $this->put(route('sales-documents.update-issued', $invoice), $payload)->assertSessionHasErrors('correction_reason');
        $this->assertSame('242.00', $invoice->fresh()->amount);
        $payload = $this->payload($invoice->fresh());
        $payload['lines'][0]['id'] = $sourceLine->id;
        $this->put(route('sales-documents.update-issued', $invoice), $payload)->assertSessionHasErrors('lines.0.id');
    }

    public function test_stale_payment_submission_rechecks_the_corrected_invoice_balance(): void
    {
        $invoice = $this->invoice();
        $payload = $this->payload($invoice);
        $payload['lines'][0]['unit_price'] = 50;
        $this->put(route('sales-documents.update-issued', $invoice), $payload)->assertSessionHasNoErrors();
        $request = Request::create('/', 'POST', ['paid_at' => '2026-09-15', 'amount' => 100, 'method' => 'cash']);
        $request->setUserResolver(fn () => auth()->user());
        try {
            app(SalesDocumentController::class)->storePayment($request, $invoice, app(AccountingSynchronizer::class));
            $this->fail('A stale payment must not exceed the corrected invoice balance.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $this->assertSame('60.50', $invoice->fresh()->amount);
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_missing_reason_and_existing_credit_notes_are_rejected(): void
    {
        $invoice = $this->invoice();
        $payload = $this->payload($invoice);
        $payload['correction_reason'] = '';
        $this->put(route('sales-documents.update-issued', $invoice), $payload)->assertSessionHasErrors('correction_reason');
        $this->post(route('sales-documents.credit-note', $invoice))->assertSessionHasNoErrors();
        $this->put(route('sales-documents.update-issued', $invoice), $this->payload($invoice))->assertSessionHasErrors('correction_reason');
    }
}
