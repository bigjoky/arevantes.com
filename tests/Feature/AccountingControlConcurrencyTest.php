<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingControlController;
use App\Models\AccountingEntryCorrection;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\SalesDocument;
use App\Models\User;
use App\Services\Accounting\AccountingAccountPolicy;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\JournalCorrection;
use App\Services\Accounting\JournalPoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AccountingControlConcurrencyTest extends TestCase
{
    public function test_real_workers_preserve_account_uniqueness_assignments_and_idempotent_corrections(): void
    {
        if (DB::getDriverName() !== 'pgsql' || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_ops01_test')) {
            $this->markTestSkipped('Requires marked disposable local PostgreSQL.');
        }
        $this->assertSame('arevantes:ops01:synthetic-disposable:v1', DB::selectOne("SELECT shobj_description(oid,'pg_database') AS marker FROM pg_database WHERE datname=current_database()")->marker);
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        foreach (['account', 'assign', 'same_request', 'different_requests', 'cancel_race'] as $scenario) {
            $user = User::factory()->create(['role' => 'admin']);
            $this->actingAs($user);
            $code = '629'.random_int(900000, 999999);
            $replacement = LedgerAccount::create(['code' => $code, 'name' => 'Synthetic race replacement', 'type' => 'expense', 'is_active' => true, 'is_postable' => true, 'source' => 'custom']);
            $entry = app(JournalPoster::class)->post('2026-09-21', 'Synthetic race original', [['account_code' => '629', 'debit' => 100, 'credit' => 0], ['account_code' => '572', 'debit' => 0, 'credit' => 100]], $user->id);
            $document = null;
            $payment = null;
            $person = null;
            $revision = null;
            if ($scenario === 'assign') {
                $person = Person::create(['category' => 'customer', 'first_name' => 'Synthetic concurrent customer']);
                $replacement = LedgerAccount::create(['code' => '430'.random_int(900000, 999999), 'name' => 'Synthetic concurrent customer account', 'type' => 'asset', 'is_active' => true, 'is_postable' => true, 'source' => 'custom']);
                $revision = app(AccountingControlController::class)->assignments(Request::create('/'), 'customer', $person->id, app(AccountingAccountPolicy::class))->getData(true)['revision'];
            }
            if ($scenario === 'cancel_race') {
                $document = SalesDocument::create(['type' => 'invoice', 'number' => 'SYN-RACE-'.Str::uuid(), 'status' => 'paid', 'locked_at' => now(), 'issue_date' => '2026-09-21', 'subtotal' => 100, 'tax_amount' => 0, 'amount' => 100, 'paid_amount' => 100, 'customer_snapshot' => []]);
                $payment = $document->payments()->create(['paid_at' => '2026-09-21', 'amount' => 100, 'method' => 'bank_transfer']);
                app(AccountingSynchronizer::class)->syncCustomerPayment($payment, $user->id);
                $entry = $payment->accountingEntry;
                $replacement = LedgerAccount::create(['code' => '572'.random_int(900000, 999999), 'name' => 'Synthetic concurrent treasury account', 'type' => 'asset', 'is_active' => true, 'is_postable' => true, 'source' => 'custom']);
            }
            $line = $entry->lines()->with('account')->whereHas('account', fn ($q) => $q->where('code', $scenario === 'cancel_race' ? '572' : '629'))->firstOrFail();
            $dir = sys_get_temp_dir().'/arevantes-acc01-race-'.Str::uuid();
            File::makeDirectory($dir);
            $workers = [];
            $key = (string) Str::uuid();
            try {
                foreach ([0, 1] as $i) {
                    $mode = $scenario === 'account' ? 'account' : ($scenario === 'assign' ? 'assign' : ($scenario === 'cancel_race' && $i === 1 ? 'cancel' : 'correct'));
                    $data = ['entry_date' => '2026-09-21', 'description' => 'Synthetic race annotation', 'notes' => null, 'reference' => null, 'reason' => 'Synthetic concurrent account correction', 'operation_key' => $scenario === 'different_requests' ? (string) Str::uuid() : $key, 'revision' => app(JournalCorrection::class)->revision($entry), 'lines' => [['id' => $line->id, 'account_code' => $replacement->code, 'description' => 'Synthetic race line']]];
                    if ($mode === 'assign') {
                        $data = ['role' => 'receivable', 'account_code' => $replacement->code, 'reason' => 'Synthetic explicit concurrent assignment', 'revision' => $revision, 'confirmed' => true];
                    }
                    $x = ['directory' => $dir, 'mode' => $mode, 'user_id' => $user->id, 'entry_id' => $entry->id, 'code' => '626991001', 'person_id' => $person?->id, 'document_id' => $document?->id, 'payment_id' => $payment?->id, 'payment_revision' => $payment?->cancellationRevision(), 'key' => (string) Str::uuid(), 'data' => $data];
                    $worker = new Process([PHP_BINARY, base_path('tests/Support/accounting-control-worker.php'), json_encode($x, JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
                    $worker->start();
                    $workers[] = $worker;
                }
                $deadline = microtime(true) + 10;
                while (count(glob($dir.'/ready-*')) < 2 && microtime(true) < $deadline) {
                    usleep(10000);
                }$this->assertCount(2, glob($dir.'/ready-*'));
                file_put_contents($dir.'/go', 'go');
                foreach ($workers as $w) {
                    $w->wait();
                    $this->assertTrue($w->isSuccessful(), $w->getErrorOutput());
                }
                $results = array_map(fn ($f) => file_get_contents($f), glob($dir.'/result-*'));
                $this->assertCount(2, $results);
                if ($scenario === 'account') {
                    $this->assertSame(['ok', 'ok'], $results);
                    $this->assertSame(1, LedgerAccount::where('code', '626991001')->count());
                } elseif ($scenario === 'assign') {
                    $this->assertSame(1, DB::table('accounting_account_assignments')->where('entity_id', $person->id)->count());
                    $this->assertSame($replacement->code, $person->fresh()->receivable_account_code);
                } else {
                    $count = AccountingEntryCorrection::where('journal_entry_id', $entry->id)->count();
                    $this->assertLessThanOrEqual(1, $count);
                    if ($scenario === 'same_request') {
                        $this->assertSame(['ok', 'ok'], $results);
                        $this->assertSame(1, $count);
                    } elseif ($scenario === 'different_requests') {
                        $this->assertContains('blocked', $results);
                        $this->assertSame(1, $count);
                    } else {
                        $voided = DB::table('sales_document_payments')->where('id', $payment->id)->value('voided_at');
                        $this->assertTrue(($voided !== null && $count === 0) || ($voided === null && $count === 1));
                    }
                }
                $this->assertEqualsWithDelta(100, (float) $entry->fresh()->lines()->sum('debit'), 0.001);
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
