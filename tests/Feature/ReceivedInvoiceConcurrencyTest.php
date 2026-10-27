<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReceivedInvoiceConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_eco02_concurrency_test')) {
            $this->markTestSkipped('Requires a disposable local ECO-02 PostgreSQL concurrency database.');
        }
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true])->assertSuccessful();
    }

    private function purchase(string $number = 'EXT-ONE'): array
    {
        $supplier = Supplier::create(['legal_name' => 'Synthetic issuer '.bin2hex(random_bytes(5)), 'country' => 'ES', 'tax_id' => 'B12345678']);

        return ['supplier_id' => $supplier->id, 'type' => 'supplier_invoice', 'supplier_number' => $number, 'number' => 'SYN-'.bin2hex(random_bytes(5)), 'issue_date' => today()->toDateString(), 'status' => 'registered', 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100];
    }

    private function expense(): array
    {
        return ['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => 'invoice', 'issuer_tax_id' => 'b.12345678', 'issuer_country' => 'ES', 'document_number' => 'EXT-ONE', 'number' => 'SYN-EXP', 'expense_date' => today()->toDateString(), 'status' => 'pending', 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100];
    }

    private function race(array $operations): array
    {
        $directory = sys_get_temp_dir().'/arevantes-eco02-'.bin2hex(random_bytes(8));
        File::makeDirectory($directory);
        $actor = User::factory()->create(['role' => 'admin']);
        $workers = [];
        try {
            foreach ($operations as $operation) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/received-invoice-worker.php'), json_encode([...$operation, 'actor' => $actor->id, 'directory' => $directory], JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
                $worker->start();
                $workers[] = $worker;
            }
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            File::deleteDirectory($directory);
        }
    }

    public function test_purchase_and_expense_cannot_commit_two_obligations_or_two_journals(): void
    {
        $results = $this->race([['operation' => 'purchase', 'attributes' => $this->purchase()], ['operation' => 'expense', 'attributes' => $this->expense()]]);
        $this->assertSame(1, collect($results)->where('status', 'ok')->count());
        $this->assertSame(1, collect($results)->where('status', 'duplicate')->count());
        $this->assertSame(1, PurchaseDocument::count() + Expense::count());
        $this->assertSame(1, JournalEntry::count());
        $this->assertSame(1, DB::table('received_invoice_identities')->count());
    }

    public function test_two_supplier_records_with_same_tax_id_share_the_identity_under_concurrency(): void
    {
        $results = $this->race([['operation' => 'purchase', 'attributes' => $this->purchase()], ['operation' => 'purchase', 'attributes' => $this->purchase()]]);
        $this->assertSame(1, collect($results)->where('status', 'duplicate')->count());
        $this->assertSame(1, PurchaseDocument::count());
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_simultaneous_posting_retries_keep_one_journal(): void
    {
        $invoice = PurchaseDocument::create($this->purchase());
        $results = $this->race([['operation' => 'post', 'id' => $invoice->id], ['operation' => 'post', 'id' => $invoice->id]]);
        $this->assertSame(1, collect($results)->sum('posted'));
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_equal_amounts_with_distinct_numbers_can_commit_concurrently(): void
    {
        $results = $this->race([['operation' => 'purchase', 'attributes' => $this->purchase('EXT-A')], ['operation' => 'purchase', 'attributes' => $this->purchase('EXT-B')]]);
        $this->assertSame(2, collect($results)->where('status', 'ok')->count());
        $this->assertSame(2, PurchaseDocument::count());
        $this->assertSame(2, JournalEntry::count());
    }
}
