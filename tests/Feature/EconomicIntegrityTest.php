<?php

namespace Tests\Feature;

use App\Http\Controllers\FiscalSettingsController;
use App\Http\Controllers\SalesDocumentController;
use App\Models\DocumentSeries;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\User;
use App\Services\Documents\DocumentPdf;
use App\Services\Documents\QuoteBillingPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EconomicIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private array $archiveDirectories = [];

    private function isolatedArchive(): void
    {
        $root = sys_get_temp_dir().'/arevantes-economic-pdf-'.bin2hex(random_bytes(8));
        $this->archiveDirectories[] = $root;
        Storage::set(config('filesystems.private'), Storage::build(['driver' => 'local', 'root' => $root, 'throw' => true]));
    }

    protected function tearDown(): void
    {
        foreach ($this->archiveDirectories as $root) {
            File::deleteDirectory($root);
        }
        parent::tearDown();
    }

    private function draft(string $amount = '121.00'): SalesDocument
    {
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Synthetic economic fixture']);
        $invoice = SalesDocument::create(['type' => 'invoice', 'person_id' => $customer->id, 'number' => 'SYNTHETIC-'.Str::random(12), 'status' => 'draft', 'issue_date' => today(), 'due_date' => today()->addDays(30), 'subtotal' => $amount, 'tax_amount' => 0, 'amount' => $amount, 'paid_amount' => 0, 'customer_snapshot' => []]);
        $invoice->lines()->create(['position' => 1, 'description' => 'Synthetic service', 'quantity' => 1, 'unit_price' => $amount, 'discount_percent' => 0, 'tax_percent' => 0, 'subtotal' => $amount, 'tax_amount' => 0, 'total' => $amount]);

        return $invoice;
    }

    public function test_retried_draft_creation_returns_one_document_and_rejects_changed_payload(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Synthetic retry']);
        $payload = ['creation_key' => (string) Str::uuid(), 'type' => 'invoice', 'person_id' => $customer->id, 'issue_date' => today()->toDateString(), 'lines' => [['description' => 'Synthetic', 'quantity' => '3', 'unit_price' => '0.333', 'discount_percent' => 0, 'tax_percent' => 21]]];
        foreach (range(1, 2) as $attempt) {
            $this->post(route('sales-documents.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(1, SalesDocument::count());
        $this->assertSame('1.21', SalesDocument::first()->amount);
        $payload['lines'][0]['unit_price'] = '10';
        $this->post(route('sales-documents.store'), $payload)->assertSessionHasErrors('creation_key');
        $this->assertSame(1, SalesDocument::count());
    }

    public function test_partial_milestone_retry_does_not_allocate_twice_but_a_new_intent_can(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Synthetic milestone']);
        $quote = Quote::create(['person_id' => $customer->id, 'created_by' => $actor->id, 'number' => 'SYNTHETIC-QUOTE', 'status' => 'accepted', 'issue_date' => today(), 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100, 'customer_snapshot' => []]);
        $quote->lines()->create(['position' => 1, 'description' => 'Synthetic', 'is_selected' => true, 'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100, 'discount_percent' => 0, 'tax_percent' => 0, 'tax_amount' => 0, 'total' => 100]);
        $payload = ['creation_key' => (string) Str::uuid(), 'type' => 'invoice', 'billing_amount' => '40.00'];
        $this->actingAs($actor);
        foreach (range(1, 2) as $attempt) {
            $this->post(route('quotes.convert', $quote), $payload)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(1, $quote->salesDocuments()->count());
        $payload['creation_key'] = (string) Str::uuid();
        $this->post(route('quotes.convert', $quote), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $quote->salesDocuments()->count());
        $this->assertEquals(80, $quote->salesDocuments()->sum('amount'));
        $this->assertSame(0, $quote->salesDocuments()->whereNotNull('issued_at')->count());
    }

    public function test_stale_accepted_quote_cannot_create_a_document_after_acceptance_was_removed(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Synthetic stale quote']);
        $quote = Quote::create(['person_id' => $customer->id, 'number' => 'SYNTHETIC-STALE', 'status' => 'accepted', 'issue_date' => today(), 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100]);
        Quote::whereKey($quote->id)->update(['status' => 'draft']);
        $request = Request::create('/', 'POST', ['type' => 'order']);
        $request->setUserResolver(fn () => $actor);
        try {
            app(SalesDocumentController::class)->convert($request, $quote, app(QuoteBillingPlan::class));
            $this->fail('Stale acceptance must not be used.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame(0, SalesDocument::count());
    }

    public function test_stale_series_settings_cannot_rewind_the_counter_after_issuing(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $stale = DocumentSeries::where('document_type', 'invoice')->firstOrFail();
        $this->actingAs($actor);
        $invoice = $this->draft();
        $this->post(route('sales-documents.issue', $invoice), ['issue_date' => today()->toDateString()])->assertRedirect();
        $request = Request::create('/', 'PUT', ['name' => 'Synthetic stale series edit', 'prefix' => 'STALE-PREFIX', 'next_number' => $stale->next_number, 'is_active' => true]);
        $request->setUserResolver(fn () => $actor);
        try {
            app(FiscalSettingsController::class)->updateSeries($request, $stale);
            $this->fail('A stale series form must not rewind an issued counter or change its used prefix.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertGreaterThan($stale->next_number, $stale->fresh()->next_number);
        $this->assertNotSame('STALE-PREFIX', $stale->fresh()->prefix);
    }

    public function test_credit_issue_updates_original_status_without_fabricating_a_receipt(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $invoice = $this->draft();
        $this->post(route('sales-documents.issue', $invoice), ['issue_date' => today()->toDateString()])->assertRedirect();
        $this->post(route('sales-documents.credit-note', $invoice))->assertRedirect();
        $credit = $invoice->childDocuments()->firstOrFail();
        $this->post(route('sales-documents.issue', $credit), ['issue_date' => today()->toDateString()])->assertRedirect();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->paid_amount);
        $this->assertSame(0, $invoice->payments()->count());
        $this->assertSame(1, $credit->accountingEntry()->count());
    }

    public function test_unposted_receipt_cannot_be_removed_without_treasury_permission(): void
    {
        $invoice = $this->draft();
        $invoice->update(['collect_before_invoice' => true]);
        $receipt = $invoice->payments()->create(['paid_at' => today(), 'amount' => '10.00', 'method' => 'cash']);
        $profile = PermissionProfile::create(['name' => 'Operations only', 'slug' => 'operations-only-test']);
        $profile->permissions()->attach(Permission::where('key', 'manage-installations')->firstOrFail());
        $user = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $profile->id]);
        $this->actingAs($user)->delete(route('sales-documents.payments.destroy', [$invoice, $receipt]))->assertForbidden();
        $this->assertDatabaseHas('sales_document_payments', ['id' => $receipt->id]);
    }

    public function test_pdf_failure_rolls_back_issue_number_and_accounting(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $invoice = $this->draft();
        $before = $invoice->fresh()->getAttributes();
        $entries = JournalEntry::count();
        $this->mock(DocumentPdf::class)->shouldReceive('renderForArchiving')->once()->andThrow(new \RuntimeException('Synthetic render failure'));
        $this->post(route('sales-documents.issue', $invoice), ['issue_date' => today()->toDateString()])->assertStatus(500);
        $this->assertSame($before, $invoice->fresh()->getAttributes());
        $this->assertSame($entries, JournalEntry::count());
    }

    public function test_failure_after_archiving_leaves_no_orphan_pdf_or_number(): void
    {
        $this->isolatedArchive();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $invoice = $this->draft();
        $number = $invoice->number;
        $counters = DocumentSeries::orderBy('id')->pluck('next_number', 'id')->all();
        SalesDocument::saving(function ($document): void {
            if ($document->archived_pdf_path) {
                throw new \RuntimeException('Synthetic failure after PDF upload');
            }
        });
        $this->post(route('sales-documents.issue', $invoice), ['issue_date' => today()->toDateString()])->assertStatus(500);
        $this->assertSame('draft', $invoice->fresh()->status);
        $this->assertSame($number, $invoice->fresh()->number);
        $this->assertSame([], Storage::disk(config('filesystems.private'))->allFiles('issued-invoices'));
        $this->assertSame($counters, DocumentSeries::orderBy('id')->pluck('next_number', 'id')->all());
    }

    public function test_existing_tampered_archive_is_not_silently_reused_or_overwritten(): void
    {
        $this->isolatedArchive();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $invoice = $this->draft();
        $expected = 'Synthetic issued archive';
        $path = 'issued-invoices/'.$invoice->id.'/'.hash('sha256', $expected).'.pdf';
        Storage::disk(config('filesystems.private'))->put($path, 'Synthetic corrupt evidence');
        $this->mock(DocumentPdf::class)->shouldReceive('renderForArchiving')->once()->andReturn($expected);
        $this->post(route('sales-documents.issue', $invoice), ['issue_date' => today()->toDateString()])->assertStatus(500);
        $this->assertSame('draft', $invoice->fresh()->status);
        $this->assertSame('Synthetic corrupt evidence', Storage::disk(config('filesystems.private'))->get($path));
        $this->assertFalse($invoice->accountingEntry()->exists());
    }

    public function test_retried_key_records_one_receipt_and_one_journal_event(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $invoice = $this->draft('100.00');
        $this->post(route('sales-documents.issue', $invoice), ['issue_date' => today()->toDateString()])->assertRedirect();
        $payload = ['idempotency_key' => (string) Str::uuid(), 'paid_at' => today()->toDateString(), 'amount' => '25.01', 'method' => 'cash', 'reference' => 'Synthetic reusable bank text'];
        foreach (range(1, 2) as $attempt) {
            $this->post(route('sales-documents.payments.store', $invoice), $payload)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame('25.01', $invoice->fresh()->paid_amount);
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame(1, $invoice->payments()->first()->accountingEntry()->count());
        $payload['amount'] = '26.00';
        $this->post(route('sales-documents.payments.store', $invoice), $payload)->assertSessionHasErrors('amount');
        $this->assertSame(1, $invoice->payments()->count());
    }
}
