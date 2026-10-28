<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Banking\ControlledReconciliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ControlledBankReconciliationConcurrencyTest extends TestCase
{
    public function test_bank_receipt_and_cancellation_locks_preserve_balances_under_real_races(): void
    {
        if (DB::getDriverName() !== 'pgsql' || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_ops01_test')) {
            $this->markTestSkipped('Requires the marked local disposable PostgreSQL database.');
        }
        $this->assertSame('arevantes:ops01:synthetic-disposable:v1', DB::selectOne("SELECT shobj_description(oid,'pg_database') AS marker FROM pg_database WHERE datname=current_database()")->marker);
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        foreach (['same_bank', 'same_receipt', 'cancel_vs_match', 'same_request'] as $scenario) {
            $user = User::factory()->create(['role' => 'admin']);
            $account = BankAccount::create(['name' => 'SYN race bank', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
            $document = SalesDocument::create(['type' => 'invoice', 'number' => 'SYN-'.Str::uuid(), 'status' => 'paid', 'locked_at' => now(), 'issue_date' => '2026-09-20', 'amount' => 200, 'subtotal' => 200, 'tax_amount' => 0, 'paid_amount' => 200, 'customer_snapshot' => []]);
            $payments = [];
            $banks = [];
            foreach ([0, 1] as $i) {
                $payments[] = $document->payments()->create(['paid_at' => '2026-09-21', 'amount' => 100, 'method' => 'bank_transfer']);
                app(AccountingSynchronizer::class)->syncCustomerPayment($payments[$i], $user->id);
                $banks[] = $account->transactions()->create(['booked_on' => '2026-09-21', 'amount' => 100, 'currency' => 'EUR', 'description' => 'SYN race', 'import_hash' => hash('sha256', (string) Str::uuid()), 'status' => 'pending']);
            }
            $document->update(['paid_amount' => '200.00']);
            $directory = sys_get_temp_dir().'/arevantes-bank-race-'.Str::uuid();
            File::makeDirectory($directory);
            $workers = [];
            $key = (string) Str::uuid();
            try {
                foreach ([0, 1] as $i) {
                    $bank = $scenario === 'same_receipt' ? $banks[$i] : $banks[0];
                    $payment = $scenario === 'same_bank' ? $payments[$i] : $payments[0];
                    $x = ['directory' => $directory, 'user_id' => $user->id, 'bank_id' => $bank->id, 'account_id' => $account->id, 'document_id' => $document->id, 'payment_id' => $payment->id, 'payment_revision' => $payment->cancellationRevision(), 'revision' => app(ControlledReconciliation::class)->revision($bank), 'amount' => '100.00', 'key' => $scenario === 'same_request' ? $key : (string) Str::uuid(), 'mode' => $scenario === 'cancel_vs_match' && $i === 1 ? 'cancel' : 'match'];
                    $worker = new Process([PHP_BINARY, base_path('tests/Support/bank-reconciliation-worker.php'), json_encode($x, JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
                    $worker->start();
                    $workers[] = $worker;
                }
                $deadline = microtime(true) + 10;
                while (count(glob($directory.'/ready-*')) < 2 && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertCount(2, glob($directory.'/ready-*'));
                file_put_contents($directory.'/go', 'go');
                foreach ($workers as $worker) {
                    $worker->wait();
                    $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                }
                $results = array_map('file_get_contents', glob($directory.'/result-*'));
                sort($results);
                $this->assertSame($scenario === 'same_request' ? ['ok', 'ok'] : ['blocked', 'ok'], $results);
                $matches = BankReconciliation::whereIn('bank_transaction_id', [$banks[0]->id, $banks[1]->id])->count();
                $voided = SalesDocumentPayment::withoutGlobalScope('active_customer_payment')->whereKey($payments[0]->id)->whereNotNull('voided_at')->count();
                $this->assertSame(1, $matches + $voided);
                foreach ($banks as $bank) {
                    $this->assertFalse(app(ControlledReconciliation::class)->bankRemaining($bank->fresh())->isNegative());
                    $this->assertSame('100.00', $bank->fresh()->amount);
                }
                $this->assertEquals((float) $document->payments()->sum('amount'), (float) $document->fresh()->paid_amount);
            } finally {
                foreach ($workers as $worker) {
                    if ($worker->isRunning()) {
                        $worker->stop();
                    }
                }File::deleteDirectory($directory);
            }
        }
    }
}
