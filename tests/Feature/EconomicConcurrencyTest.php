<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Documents\SalesDocumentConversion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class EconomicConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_economic_concurrency_test')) {
            $this->markTestSkipped('Requires a disposable local economic concurrency PostgreSQL database.');
        }
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true])->assertSuccessful();
    }

    private function document(string $type = 'invoice', string $amount = '100.00'): SalesDocument
    {
        $person = Person::create(['category' => 'customer', 'first_name' => 'Synthetic economics']);
        $document = SalesDocument::create(['type' => $type, 'person_id' => $person->id, 'number' => 'SYNTHETIC-'.Str::random(10), 'status' => 'draft', 'issue_date' => today(), 'subtotal' => $amount, 'tax_amount' => 0, 'amount' => $amount, 'paid_amount' => 0, 'customer_snapshot' => []]);
        $document->lines()->create(['position' => 1, 'description' => 'Synthetic', 'quantity' => 10, 'unit_price' => (float) $amount / 10, 'subtotal' => $amount, 'discount_percent' => 0, 'tax_percent' => 0, 'tax_amount' => 0, 'total' => $amount]);

        return $document;
    }

    private function race(array $operations, ?callable $hold = null): array
    {
        $directory = sys_get_temp_dir().'/arevantes-economic-'.bin2hex(random_bytes(8));
        File::makeDirectory($directory);
        $actor = User::factory()->create(['role' => 'admin']);
        $workers = [];
        $names = ['economic-'.bin2hex(random_bytes(6)), 'economic-'.bin2hex(random_bytes(6))];
        try {
            if ($hold) {
                DB::beginTransaction();
                $hold();
            }
            foreach ($operations as $index => $operation) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/economic-concurrency-worker.php'), $names[$index], json_encode([...$operation, 'user_id' => $actor->id, 'directory' => $directory], JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
                $worker->start();
                $workers[] = $worker;
            }
            if ($hold) {
                $deadline = microtime(true) + 10;
                do {
                    DB::select('SELECT pg_stat_clear_snapshot()');
                    $waiting = DB::table('pg_stat_activity')->whereIn('application_name', $names)->where('wait_event_type', 'Lock')->count();
                    if ($waiting === 2) {
                        break;
                    }
                    usleep(20000);
                } while (microtime(true) < $deadline);
                $this->assertSame(2, $waiting, implode(' ', array_map(fn ($worker) => $worker->getErrorOutput(), $workers)));
                DB::commit();
            }
            $outcomes = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $outcomes[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $outcomes;
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            File::deleteDirectory($directory);
        }
    }

    public function test_order_and_its_delivery_cannot_invoice_the_same_capacity_concurrently(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $order = $this->document('order');
        $delivery = app(SalesDocumentConversion::class)->convert($order, 'delivery_note', [$order->lines->first()->id => 10], $actor->id);
        $delivery->update(['status' => 'delivered']);
        $outcomes = $this->race([
            ['operation' => 'convert', 'document_id' => $order->id, 'line_id' => $order->lines->first()->id],
            ['operation' => 'convert', 'document_id' => $delivery->id, 'line_id' => $delivery->lines->first()->id],
        ]);
        $this->assertSame(1, SalesDocument::where('type', 'invoice')->count());
        $this->assertSame('100.00', (string) SalesDocument::where('type', 'invoice')->firstOrFail()->amount);
        $this->assertSame(1, collect($outcomes)->where('outcome', 'rejected')->count());
    }

    public function test_distinct_invoices_receive_distinct_numbers_and_one_journal_each(): void
    {
        $one = $this->document();
        $two = $this->document();
        $payload = ['issue_date' => today()->toDateString()];
        $this->race([['operation' => 'issue', 'document_id' => $one->id, 'payload' => $payload], ['operation' => 'issue', 'document_id' => $two->id, 'payload' => $payload]]);
        $this->assertNotSame($one->fresh()->number, $two->fresh()->number);
        $this->assertSame(2, JournalEntry::where('source_type', $one->getMorphClass())->where('event', 'document')->count());
        $this->assertNotNull($one->fresh()->archived_pdf_hash);
        $this->assertNotNull($two->fresh()->archived_pdf_hash);
    }

    public function test_repeated_issue_of_the_same_invoice_has_no_second_economic_effect(): void
    {
        $invoice = $this->document();
        $operation = ['operation' => 'issue', 'document_id' => $invoice->id, 'payload' => ['issue_date' => today()->toDateString()]];
        $outcomes = $this->race([$operation, $operation], fn () => SalesDocument::whereKey($invoice->id)->lockForUpdate()->firstOrFail());
        $this->assertSame(1, $invoice->accountingEntry()->count());
        $this->assertSame(1, collect($outcomes)->where('outcome', 'rejected')->count());
    }

    public function test_simultaneous_receipts_cannot_exceed_the_invoice_balance(): void
    {
        $invoice = $this->document();
        $invoice->update(['collect_before_invoice' => true]);
        $operation = ['operation' => 'payment', 'document_id' => $invoice->id, 'payload' => ['paid_at' => today()->toDateString(), 'amount' => '60.00', 'method' => 'cash']];
        $outcomes = $this->race([$operation, $operation], fn () => SalesDocument::whereKey($invoice->id)->lockForUpdate()->firstOrFail());
        $this->assertSame('60.00', $invoice->fresh()->paid_amount);
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame(1, collect($outcomes)->where('outcome', 'rejected')->count());
    }

    public function test_same_receipt_key_is_idempotent_under_concurrency(): void
    {
        $invoice = $this->document();
        $invoice->update(['collect_before_invoice' => true]);
        $operation = ['operation' => 'payment', 'document_id' => $invoice->id, 'payload' => ['idempotency_key' => (string) Str::uuid(), 'paid_at' => today()->toDateString(), 'amount' => '0.03', 'method' => 'cash', 'reference' => 'Same external operation']];
        $this->race([$operation, $operation], fn () => SalesDocument::whereKey($invoice->id)->lockForUpdate()->firstOrFail());
        $this->assertSame('0.03', $invoice->fresh()->paid_amount);
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame(1, $invoice->payments()->first()->accountingEntry()->count());
    }

    public function test_same_receipt_key_on_different_invoices_is_a_validation_error_not_a_duplicate_or_500(): void
    {
        $one = $this->document();
        $two = $this->document();
        $one->update(['collect_before_invoice' => true]);
        $two->update(['collect_before_invoice' => true]);
        $payload = ['idempotency_key' => (string) Str::uuid(), 'paid_at' => today()->toDateString(), 'amount' => '0.03', 'method' => 'cash'];
        $outcomes = $this->race([['operation' => 'payment_key_race', 'document_id' => $one->id, 'payload' => $payload], ['operation' => 'payment_key_race', 'document_id' => $two->id, 'payload' => $payload]]);
        $this->assertSame(1, SalesDocumentPayment::count());
        $this->assertEquals(0.03, SalesDocumentPayment::sum('amount'));
        $this->assertSame(1, collect($outcomes)->where('outcome', 'rejected')->where('status', 422)->count());
        $this->assertSame(1, JournalEntry::where('source_type', (new SalesDocumentPayment)->getMorphClass())->where('event', 'payment')->count());
    }

    public function test_same_creation_key_cannot_create_two_copies_under_concurrency(): void
    {
        $source = $this->document();
        $operation = ['operation' => 'duplicate', 'document_id' => $source->id, 'payload' => ['creation_key' => (string) Str::uuid()]];
        $this->race([$operation, $operation], fn () => SalesDocument::whereKey($source->id)->lockForUpdate()->firstOrFail());
        $this->assertSame(2, SalesDocument::count());
        $this->assertSame(1, SalesDocument::whereNotNull('creation_key')->count());
        $this->assertSame(0, JournalEntry::where('source_type', $source->getMorphClass())->count());
    }

    public function test_concurrent_credit_preparation_returns_one_draft(): void
    {
        $invoice = $this->document();
        $invoice->update(['status' => 'issued', 'locked_at' => now()]);
        $operation = ['operation' => 'credit', 'document_id' => $invoice->id];
        $this->race([$operation, $operation], fn () => SalesDocument::whereKey($invoice->id)->lockForUpdate()->firstOrFail());
        $this->assertSame(1, $invoice->childDocuments()->where('type', 'credit_note')->count());
        $this->assertSame('-100.00', $invoice->childDocuments()->first()->amount);
    }

    private function supplierInvoice(): PurchaseDocument
    {
        return EconomicFixture::purchase(['supplier_id' => EconomicFixture::supplier(['legal_name' => 'Synthetic supplier', 'country' => 'ES'])->id, 'type' => 'supplier_invoice', 'number' => 'SYNTHETIC-SUP-'.Str::random(8), 'status' => 'issued', 'issue_date' => today(), 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100, 'paid_amount' => 0, 'supplier_snapshot' => []]);
    }

    public function test_supplier_payment_posting_and_deletion_do_not_leave_orphan_accounting(): void
    {
        $invoice = $this->supplierInvoice();
        $receipt = $invoice->payments()->create(['paid_at' => today(), 'amount' => '10.00', 'method' => 'cash']);
        $operation = ['document_id' => $invoice->id, 'receipt_id' => $receipt->id];
        $this->race([['operation' => 'supplier_delete', ...$operation], ['operation' => 'supplier_post', ...$operation]], fn () => PurchaseDocument::whereKey($invoice->id)->lockForUpdate()->firstOrFail());
        $exists = SupplierPayment::whereKey($receipt->id)->exists();
        $entries = JournalEntry::where('source_type', $receipt->getMorphClass())->where('source_id', $receipt->id)->count();
        $this->assertSame($exists ? 1 : 0, $entries);
    }

    public function test_supplier_repeated_key_on_different_invoices_rejects_without_500_or_duplicate(): void
    {
        $one = $this->supplierInvoice();
        $two = $this->supplierInvoice();
        $account = BankAccount::create(['name' => 'Synthetic bank', 'currency' => 'EUR', 'is_active' => true, 'ledger_account_id' => LedgerAccount::where('code', '572')->firstOrFail()->id]);
        $payload = ['idempotency_key' => (string) Str::uuid(), 'paid_at' => today()->toDateString(), 'amount' => '0.03', 'method' => 'bank_transfer', 'funding_source_type' => 'bank_account', 'funding_source_id' => $account->id];
        $outcomes = $this->race([['operation' => 'supplier_key_race', 'document_id' => $one->id, 'payload' => $payload], ['operation' => 'supplier_key_race', 'document_id' => $two->id, 'payload' => $payload]]);
        $this->assertSame(1, SupplierPayment::count());
        $this->assertSame(1, collect($outcomes)->where('outcome', 'rejected')->where('status', 422)->count());
        $this->assertSame(1, JournalEntry::where('source_type', (new SupplierPayment)->getMorphClass())->where('event', 'payment')->count());
    }

    public function test_receipt_posting_and_deletion_do_not_leave_orphan_accounting(): void
    {
        $invoice = $this->document();
        $invoice->update(['collect_before_invoice' => true]);
        $receipt = $invoice->payments()->create(['paid_at' => today(), 'amount' => '10.00', 'method' => 'cash']);
        $operation = ['document_id' => $invoice->id, 'receipt_id' => $receipt->id];
        $this->race([['operation' => 'delete_receipt', ...$operation], ['operation' => 'post_receipt', ...$operation]], fn () => SalesDocument::whereKey($invoice->id)->lockForUpdate()->firstOrFail());
        $exists = SalesDocumentPayment::whereKey($receipt->id)->exists();
        $entries = JournalEntry::where('source_type', $receipt->getMorphClass())->where('source_id', $receipt->id)->count();
        $this->assertSame($exists ? 1 : 0, $entries);
    }
}
