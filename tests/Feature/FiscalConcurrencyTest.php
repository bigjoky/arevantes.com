<?php

namespace Tests\Feature;

use App\Models\DocumentSeries;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Opportunity;
use App\Models\SalesDocument;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Tax\VatSettlement;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FiscalConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'pgsql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_concurrency_test')) {
            $this->markTestSkipped('Requires an isolated PostgreSQL database ending in _concurrency_test.');
        }
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true])->assertExitCode(0);
    }

    private function fixture(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $expense = Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id,
            'created_by' => $user->id, 'number' => 'EXP-CONCURRENT', 'type' => 'invoice',
            'expense_date' => '2026-09-15', 'subtotal' => 100, 'tax_amount' => 21,
            'amount' => 121, 'status' => 'pending']);
        app(AccountingSynchronizer::class)->syncExpense($expense, $user->id);

        return $user;
    }

    /** Start both independent connections while the year lock is held, then release together. */
    private function race(string $operation, int $userId, ?int $returnId = null): array
    {
        $processes = [];
        $names = ['fiscal-race-'.bin2hex(random_bytes(6)), 'fiscal-race-'.bin2hex(random_bytes(6))];
        DB::beginTransaction();
        try {
            if ($operation === 'commercial-opportunity') {
                DB::statement('LOCK TABLE opportunities IN SHARE MODE');
            } else {
                FiscalYear::where('year', 2026)->lockForUpdate()->firstOrFail();
            }
            foreach ($names as $name) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/fiscal-concurrency-worker.php'), $name, $operation, (string) $userId, (string) $returnId], base_path(), timeout: 20);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 8;
            do {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::table('pg_stat_activity')->whereIn('application_name', $names)->where('wait_event_type', 'Lock')->count();
                if ($waiting === 2) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Both independent connections must be blocked before releasing the lock.');
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }

    public function test_concurrent_opportunities_consume_the_gap_once_and_initialize_the_normal_counter(): void
    {
        Opportunity::create(['title' => 'Existing', 'stage' => 'new', 'code' => 'OPP-2026-00014']);
        DB::table('commercial_number_reservations')->insert([
            'document_type' => 'opportunity', 'year' => 2026, 'number' => 10,
            'reason' => 'Authorized duplicate removal', 'created_at' => now(),
        ]);

        $results = $this->race('commercial-opportunity', 0);
        $numbers = array_column($results, 'result');
        sort($numbers);
        $this->assertSame(['OPP-2026-00010', 'OPP-2026-00015'], $numbers);
        $this->assertSame(16, (int) DB::table('opportunity_number_sequences')->where('year', 2026)->value('next_number'));
        $this->assertDatabaseMissing('commercial_number_reservations', ['document_type' => 'opportunity', 'consumed_at' => null]);
    }

    public function test_concurrent_quotes_consume_the_gap_once_without_skipping_eight(): void
    {
        $series = DocumentSeries::where('document_type', 'quote')->where('fiscal_year_id', FiscalYear::where('year', 2026)->firstOrFail()->id)->firstOrFail();
        $series->update(['next_number' => 8]);
        DB::table('commercial_number_reservations')->insert([
            'document_type' => 'quote', 'year' => 2026, 'number' => 6,
            'reason' => 'Authorized duplicate removal', 'created_at' => now(),
        ]);

        $results = $this->race('commercial-quote', 0);
        $numbers = array_column($results, 'result');
        sort($numbers);
        $this->assertSame(['PRE-2026-00006', 'PRE-2026-00008'], $numbers);
        $this->assertSame(9, (int) $series->fresh()->next_number);
        $this->assertDatabaseMissing('commercial_number_reservations', ['document_type' => 'quote', 'consumed_at' => null]);
    }

    public function test_concurrent_draft_creation_keeps_one_period_and_no_settlement_entries(): void
    {
        $user = $this->fixture();
        $entries = JournalEntry::count();
        $results = $this->race('draft', $user->id);
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result['status'] === 'ok')));
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result['status'] === 'rejected')));
        $this->assertDatabaseCount('tax_returns', 1);
        $this->assertSame($entries, JournalEntry::count());
    }

    public function test_concurrent_posting_keeps_one_balanced_entry(): void
    {
        $user = $this->fixture();
        $return = app(VatSettlement::class)->prepare(2026, 3, $user->id);
        app(VatSettlement::class)->review($return, $user->id, 'Reviewed');
        $entries = JournalEntry::count();
        $results = $this->race('post', $user->id, $return->id);
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result['status'] === 'ok')));
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result['status'] === 'rejected')));
        $this->assertSame($entries + 1, JournalEntry::count());
        $entry = $return->fresh()->journalEntry;
        $this->assertEquals($entry->lines->sum('debit'), $entry->lines->sum('credit'));
        $this->assertSame('prepared', $return->fresh()->status);
    }

    public function test_concurrent_numbers_are_unique_and_advance_series_twice(): void
    {
        $user = $this->fixture();
        $series = DocumentSeries::where('document_type', 'expense')->where('fiscal_year_id', FiscalYear::where('year', 2026)->firstOrFail()->id)->firstOrFail();
        $next = $series->next_number;
        $results = $this->race('number', $user->id);
        $this->assertSame(['ok', 'ok'], array_column($results, 'status'));
        $this->assertCount(2, array_unique(array_column($results, 'result')));
        $this->assertSame($next + 2, $series->fresh()->next_number);
    }

    public function test_concurrent_invoice_issue_uses_one_number_and_one_accounting_entry(): void
    {
        $user = $this->fixture();
        $invoice = SalesDocument::create(['type' => 'invoice', 'number' => 'DRAFT-CONCURRENT',
            'status' => 'draft', 'issue_date' => '2026-09-15', 'subtotal' => 100,
            'tax_amount' => 21, 'amount' => 121, 'customer_snapshot' => ['company_name' => 'Test customer']]);
        $series = DocumentSeries::where('document_type', 'invoice')->where('fiscal_year_id', FiscalYear::where('year', 2026)->firstOrFail()->id)->firstOrFail();
        $next = $series->next_number;
        $entries = JournalEntry::count();
        $results = $this->race('issue', $user->id, $invoice->id);
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result['status'] === 'ok')));
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result['status'] === 'rejected')));
        $this->assertSame('issued', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->locked_at);
        $this->assertSame($next + 1, $series->fresh()->next_number);
        $this->assertSame($entries + 1, JournalEntry::count());
    }
}
