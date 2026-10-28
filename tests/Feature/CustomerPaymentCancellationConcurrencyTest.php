<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Person;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Billing\BillingWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CustomerPaymentCancellationConcurrencyTest extends TestCase
{
    public function test_parallel_cancellations_replay_and_new_receipts_remain_consistent(): void
    {
        if (DB::getDriverName() !== 'pgsql' || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_ops01_test')) {
            $this->markTestSkipped('Requires a marked local disposable PostgreSQL database.');
        }
        $this->assertSame('arevantes:ops01:synthetic-disposable:v1', DB::selectOne("SELECT shobj_description(oid,'pg_database') AS marker FROM pg_database WHERE datname=current_database()")->marker);
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        foreach (['different_keys', 'same_key', 'new_receipt'] as $scenario) {
            $user = User::factory()->create(['role' => 'admin']);
            $customer = Person::create(['category' => 'customer', 'first_name' => 'Synthetic']);
            $document = SalesDocument::create(['type' => 'invoice', 'number' => 'SYN-'.Str::uuid(), 'person_id' => $customer->id, 'status' => 'paid', 'locked_at' => now(), 'issue_date' => '2026-09-20', 'due_date' => '2099-09-30', 'amount' => '121.00', 'subtotal' => '100.00', 'tax_amount' => '21.00', 'paid_amount' => '121.00', 'customer_snapshot' => []]);
            $payment = $document->payments()->create(['paid_at' => '2026-09-21', 'amount' => '121.00', 'method' => 'cash']);
            app(AccountingSynchronizer::class)->syncCustomerPayment($payment, $user->id);
            $directory = sys_get_temp_dir().'/arevantes-receipt-race-'.Str::uuid();
            File::makeDirectory($directory);
            $firstKey = (string) Str::uuid();
            $workers = [];
            try {
                foreach ([0, 1] as $i) {
                    $args = ['directory' => $directory, 'document_id' => $document->id, 'payment_id' => $payment->id, 'user_id' => $user->id, 'mode' => $i === 1 && $scenario === 'new_receipt' ? 'create' : 'cancel', 'key' => $i === 0 || $scenario === 'same_key' ? $firstKey : (string) Str::uuid(), 'revision' => $payment->cancellationRevision()];
                    $worker = new Process([PHP_BINARY, base_path('tests/Support/customer-receipt-worker.php'), json_encode($args, JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
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
                    $this->assertTrue($worker->isSuccessful(), $worker->getOutput().$worker->getErrorOutput());
                }
                $this->assertSame(1, AuditLog::where('event', 'customer_receipt_voided')->where('auditable_id', $payment->id)->count());
                $this->assertNotNull(SalesDocumentPayment::withoutGlobalScope('active_customer_payment')->findOrFail($payment->id)->voided_at);
                $this->assertSame(BillingWorkspace::decimal($document->payments()->sum('amount')), $document->fresh()->paid_amount);
                $this->assertContains($document->fresh()->paid_amount, ['0.00', '121.00']);
                $results = array_map('file_get_contents', glob($directory.'/result-*'));
                $this->assertCount(2, $results);
                if ($scenario === 'same_key') {
                    $this->assertSame(['ok', 'ok'], $results);
                } elseif ($scenario === 'different_keys') {
                    sort($results);
                    $this->assertSame(['blocked', 'ok'], $results);
                }
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
}
