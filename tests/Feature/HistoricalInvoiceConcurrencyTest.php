<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ExpenseCategory;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Tax\VatReport;
use App\Services\Tax\VatSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class HistoricalInvoiceConcurrencyTest extends TestCase
{
    private function fixture(): array
    {
        if (DB::getDriverName() !== 'pgsql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_reg2026_concurrency_test')) {
            $this->markTestSkipped('Requires isolated REG-2026 PostgreSQL.');
        }
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true])->assertSuccessful();
        $user = User::factory()->create(['role' => 'admin']);
        $permission = Permission::where('key', 'manage-historical-invoices-2026')->firstOrFail();
        $user->permissionProfile->permissions()->syncWithoutDetaching([$permission->id]);
        $supplier = EconomicFixture::supplier(['legal_name' => 'Concurrent synthetic supplier', 'tax_id' => 'B12345678', 'country' => 'ES', 'is_active' => true]);
        $document = ['supplier_id' => $supplier->id, 'expense_category_id' => ExpenseCategory::firstOrFail()->id, 'supplier_tax_id' => 'B12345678', 'supplier_number' => 'CONCURRENT-1', 'issue_date' => '2026-01-15', 'operation_date' => '', 'received_date' => '', 'due_date' => '', 'taxable_base' => '100', 'vat_rate' => '21', 'vat_amount' => '21', 'withholding_rate' => '0', 'withholding_amount' => '0', 'total' => '121'];
        $batch = DB::table('historical_invoice_batches')->insertGetId(['created_by' => $user->id, 'source' => 'individual', 'created_at' => now(), 'updated_at' => now()]);
        $row = DB::table('historical_invoice_rows')->insertGetId(['batch_id' => $batch, 'row_number' => 1, 'status' => 'review_required', 'document' => json_encode($document), 'errors' => '[]', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return [$user, $row, $batch, $document];
    }

    private function race(User $user, array $operations): array
    {
        $directory = sys_get_temp_dir().'/arevantes-historical-'.bin2hex(random_bytes(8));
        File::makeDirectory($directory);
        $workers = [];
        try {
            foreach ($operations as $operation) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/historical-confirm-worker.php'), json_encode([...$operation, 'user' => $user->id, 'directory' => $directory], JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
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

    public function test_two_rows_with_same_identity_cannot_both_register(): void
    {
        [$user, $row, $batch, $document] = $this->fixture();
        $second = DB::table('historical_invoice_rows')->insertGetId(['batch_id' => $batch, 'row_number' => 2, 'status' => 'review_required', 'document' => json_encode($document), 'errors' => '[]', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $results = $this->race($user, [['operation' => 'confirm', 'row' => $row], ['operation' => 'confirm', 'row' => $second]]);
        $this->assertSame(1, collect($results)->where('status', 'ok')->count());
        $this->assertSame(1, DB::table('expenses')->count());
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_two_2025_rows_with_the_same_identity_cannot_both_register(): void
    {
        [$user, $row, $batch, $document] = $this->fixture();
        FiscalYear::firstOrCreate(['year' => 2025], ['starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'status' => 'open']);
        $document['issue_date'] = '2025-01-15';
        $document['_staging_year'] = '2025';
        DB::table('historical_invoice_rows')->where('id', $row)->update(['document' => json_encode($document)]);
        $second = DB::table('historical_invoice_rows')->insertGetId(['batch_id' => $batch, 'row_number' => 2, 'status' => 'review_required', 'document' => json_encode($document), 'errors' => '[]', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $results = $this->race($user, [['operation' => 'confirm', 'row' => $row], ['operation' => 'confirm', 'row' => $second]]);
        $this->assertSame(1, collect($results)->where('status', 'ok')->count());
        $this->assertSame(1, DB::table('expenses')->count());
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_month_close_serializes_with_confirmation(): void
    {
        [$user, $row] = $this->fixture();
        $results = $this->race($user, [['operation' => 'confirm', 'row' => $row], ['operation' => 'close_month']]);
        $this->assertSame('ok', $results[1]['status']);
        $this->assertSame('closed', AccountingPeriod::where(['year' => 2026, 'month' => 3])->value('status'));
        $this->assertSame($results[0]['status'] === 'ok' ? 1 : 0, DB::table('expenses')->count());
        $this->assertSame(DB::table('expenses')->count(), JournalEntry::count());
    }

    public function test_year_close_does_not_allow_posting_into_a_closed_month_or_year(): void
    {
        [$user, $row] = $this->fixture();
        $year = FiscalYear::where('year', 2026)->firstOrFail();
        foreach (range(1, 12) as $month) {
            AccountingPeriod::updateOrCreate(['year' => 2026, 'month' => $month], ['fiscal_year_id' => $year->id, 'status' => 'closed']);
        }
        $results = $this->race($user, [['operation' => 'confirm', 'row' => $row], ['operation' => 'close_year']]);
        $this->assertSame('rejected', $results[0]['status']);
        $this->assertSame('ok', $results[1]['status']);
        $this->assertSame('closed', $year->fresh()->status);
        $this->assertSame(0, DB::table('expenses')->count());
    }

    public function test_vat_protection_and_confirmation_cannot_ignore_each_other(): void
    {
        [$user, $row] = $this->fixture();
        $source = EconomicFixture::expense(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'created_by' => $user->id, 'number' => 'EXP-VAT-SYN', 'type' => 'invoice', 'expense_date' => '2026-02-15', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'status' => 'pending']);
        app(AccountingSynchronizer::class)->syncExpense($source, $user->id);
        $return = app(VatSettlement::class)->prepare(2026, 1, $user->id);
        app(VatSettlement::class)->review($return, $user->id, 'Synthetic review');
        $results = $this->race($user, [['operation' => 'confirm', 'row' => $row], ['operation' => 'post_vat', 'return' => $return->id]]);
        $this->assertFalse($results[0]['status'] === 'ok' && $results[1]['status'] === 'ok', 'A protected return must not omit a concurrently confirmed invoice.');
        $this->assertSame($results[0]['status'] === 'ok' ? 2 : 1, DB::table('expenses')->count());
    }

    public function test_vat_draft_concurrent_with_confirmation_is_current_or_detects_the_change(): void
    {
        [$user, $row] = $this->fixture();
        $source = EconomicFixture::expense(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'created_by' => $user->id, 'number' => 'EXP-VAT-DRAFT-SYN', 'type' => 'invoice', 'expense_date' => '2026-02-15', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'status' => 'pending']);
        app(AccountingSynchronizer::class)->syncExpense($source, $user->id);
        $results = $this->race($user, [['operation' => 'confirm', 'row' => $row], ['operation' => 'prepare_vat']]);
        $this->assertSame('ok', $results[0]['status']);
        $this->assertSame('ok', $results[1]['status']);
        $return = TaxReturn::findOrFail($results[1]['result']);
        $invoiceId = $results[0]['result'];
        $included = collect($return->snapshot['sources']['received'])->contains(fn ($item) => ($item['source_id'] ?? null) === $invoiceId && ($item['source_type'] ?? null) === 'expense');
        $readiness = app(VatSettlement::class)->readiness($return, app(VatReport::class)->make(2026, 1));
        $this->assertTrue($included || ! $readiness['ready'], 'A stale draft must not appear ready to post.');
    }

    public function test_two_connections_confirm_one_row_only_once(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_reg2026_concurrency_test')) {
            $this->markTestSkipped('Requires isolated REG-2026 PostgreSQL.');
        }
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true])->assertSuccessful();
        $user = User::factory()->create(['role' => 'admin']);
        $permission = Permission::firstOrCreate(['key' => 'manage-historical-invoices-2026'], ['name' => 'Historical invoices', 'category' => 'finance', 'is_sensitive' => true]);
        $user->permissionProfile->permissions()->syncWithoutDetaching([$permission->id]);
        $supplier = EconomicFixture::supplier(['legal_name' => 'Concurrent synthetic supplier', 'tax_id' => 'B12345678', 'country' => 'ES', 'is_active' => true]);
        $document = ['supplier_id' => $supplier->id, 'expense_category_id' => ExpenseCategory::firstOrFail()->id, 'supplier_tax_id' => 'B12345678', 'supplier_number' => 'CONCURRENT-1', 'issue_date' => '2026-01-15', 'operation_date' => '', 'received_date' => '', 'due_date' => '', 'taxable_base' => '100', 'vat_rate' => '21', 'vat_amount' => '21', 'withholding_rate' => '0', 'withholding_amount' => '0', 'total' => '121'];
        $batch = DB::table('historical_invoice_batches')->insertGetId(['created_by' => $user->id, 'source' => 'individual', 'created_at' => now(), 'updated_at' => now()]);
        $row = DB::table('historical_invoice_rows')->insertGetId(['batch_id' => $batch, 'row_number' => 1, 'status' => 'review_required', 'document' => json_encode($document), 'errors' => '[]', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $directory = sys_get_temp_dir().'/arevantes-historical-'.bin2hex(random_bytes(8));
        File::makeDirectory($directory);
        $workers = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/historical-confirm-worker.php'), json_encode(['user' => $user->id, 'row' => $row, 'directory' => $directory], JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
                $worker->start();
                $workers[] = $worker;
            }
            $ids = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $ids[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR)['result'];
            }
            $this->assertSame($ids[0], $ids[1]);
            $this->assertSame(1, DB::table('expenses')->count());
            $this->assertSame(1, JournalEntry::count());
            $this->assertSame(0, DB::table('supplier_payments')->count());
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            File::deleteDirectory($directory);
        }
    }
}
