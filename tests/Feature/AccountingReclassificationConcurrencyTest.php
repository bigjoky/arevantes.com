<?php

namespace Tests\Feature;

use App\Models\AccountingEntryCorrection;
use App\Models\LedgerAccount;
use App\Models\SalesDocument;
use App\Models\User;
use App\Services\Accounting\AccountingReclassification;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\JournalPoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AccountingReclassificationConcurrencyTest extends TestCase
{
    public function test_batches_are_idempotent_and_coordinate_with_receipt_cancellation(): void
    {
        if (DB::getDriverName() !== 'pgsql' || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_ops01_test')) {
            $this->markTestSkipped('Requires marked disposable local PostgreSQL.');
        }
        $this->assertSame('arevantes:ops01:synthetic-disposable:v1', DB::selectOne("SELECT shobj_description(oid,'pg_database') AS marker FROM pg_database WHERE datname=current_database()")->marker);
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        foreach (['same', 'different', 'cancel'] as $scenario) {
            $u = User::factory()->create(['role' => 'admin']);
            $this->actingAs($u);
            $doc = null;
            $payment = null;
            if ($scenario === 'cancel') {
                $doc = SalesDocument::create(['type' => 'invoice', 'number' => 'SYN-RACE-'.Str::uuid(), 'status' => 'paid', 'locked_at' => now(), 'issue_date' => '2026-09-21', 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100, 'paid_amount' => 100, 'customer_snapshot' => []]);
                $payment = $doc->payments()->create(['amount' => 100, 'paid_at' => '2026-09-21', 'method' => 'bank_transfer']);
                app(AccountingSynchronizer::class)->syncCustomerPayment($payment, $u->id);
                $entry = $payment->accountingEntry;
                $prefix = '430';
            } else {
                $entry = app(JournalPoster::class)->post('2026-09-21', 'Synthetic batch race', [['account_code' => '629', 'debit' => 100, 'credit' => 0], ['account_code' => '572', 'debit' => 0, 'credit' => 100]], $u->id);
                $prefix = '629';
            }
            $code = $prefix.random_int(900000, 999999);
            LedgerAccount::create(['code' => $code, 'name' => 'Synthetic batch race account', 'type' => $prefix === '430' ? 'asset' : 'expense', 'is_active' => true, 'is_postable' => true, 'source' => 'custom']);
            $line = $entry->lines()->whereHas('account', fn ($q) => $q->where('code', $prefix))->firstOrFail();
            $dir = sys_get_temp_dir().'/arevantes-acc01-race-'.Str::uuid();
            File::makeDirectory($dir);
            $workers = [];
            $key = (string) Str::uuid();
            try {
                foreach ([0, 1] as $i) {
                    $data = ['operation_key' => $scenario === 'different' ? (string) Str::uuid() : $key, 'entry_date' => '2026-09-22', 'reason' => 'Synthetic concurrent supervised batch', 'items' => [['line_id' => $line->id, 'account_code' => $code]], 'confirmed' => true];
                    $plan = app(AccountingReclassification::class)->preview($data, $u);
                    $data['preview_revision'] = $plan['preview_revision'];
                    $args = ['directory' => $dir, 'mode' => $scenario === 'cancel' && $i === 1 ? 'cancel' : 'batch', 'user_id' => $u->id, 'document_id' => $doc?->id, 'payment_id' => $payment?->id, 'payment_revision' => $payment?->cancellationRevision(), 'key' => (string) Str::uuid(), 'data' => $data];
                    $w = new Process([PHP_BINARY, base_path('tests/Support/accounting-control-worker.php'), json_encode($args, JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
                    $w->start();
                    $workers[] = $w;
                }
                $deadline = microtime(true) + 10;
                while (count(glob($dir.'/ready-*')) < 2 && microtime(true) < $deadline) {
                    usleep(10000);
                }$this->assertCount(2, glob($dir.'/ready-*'));
                file_put_contents($dir.'/go', 'go');
                foreach ($workers as $w) {
                    $w->wait();
                    $this->assertTrue($w->isSuccessful(), $w->getErrorOutput());
                }$results = array_map(fn ($p) => file_get_contents($p), glob($dir.'/result-*'));
                $this->assertCount(2, $results);
                $count = AccountingEntryCorrection::where('journal_entry_id', $entry->id)->count();
                if ($scenario === 'same') {
                    $this->assertSame(['ok', 'ok'], $results);
                    $this->assertSame(1, $count);
                } elseif ($scenario === 'different') {
                    $this->assertContains('blocked', $results);
                    $this->assertSame(1, $count);
                } else {
                    $voided = DB::table('sales_document_payments')->where('id', $payment->id)->value('voided_at');
                    $this->assertTrue(($voided !== null && $count === 0) || ($voided === null && $count === 1));
                }
                $this->assertSame(0, DB::query()->fromSub(DB::table('journal_lines')->select('journal_entry_id')->groupBy('journal_entry_id')->havingRaw('sum(debit)<>sum(credit)'), 'unbalanced')->count());
            } finally {
                foreach ($workers as $w) {
                    if ($w->isRunning()) {
                        $w->stop();
                    }
                }File::deleteDirectory($dir);
            }
        }
    }
}
