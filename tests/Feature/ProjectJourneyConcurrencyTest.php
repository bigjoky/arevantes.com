<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProjectJourneyConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_journey_concurrency_test')) {
            $this->markTestSkipped('Requires a disposable local PostgreSQL journey concurrency database.');
        }
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true])->assertExitCode(0);
    }

    private function race(string $operation): array
    {
        $user = User::factory()->create(['role' => 'admin']);
        $person = Person::create(['category' => 'customer', 'first_name' => 'Prueba concurrente']);
        $opportunity = Opportunity::create(['person_id' => $person->id, 'code' => 'OPP-CONCURRENT', 'title' => 'Prueba', 'stage' => 'proposal']);
        $quote = Quote::create(['person_id' => $person->id, 'opportunity_id' => $opportunity->id, 'created_by' => $user->id, 'number' => 'PRE-CONCURRENT', 'status' => $operation === 'project' ? 'accepted' : 'draft', 'approval_status' => 'not_required', 'service_type' => 'solar', 'issue_date' => today(), 'valid_until' => today()->addDays(10), 'amount' => 121, 'subtotal' => 100, 'tax_amount' => 21, 'acceptance_payment_percentage' => 30, 'acceptance_payment_due_days' => 7]);
        $quote->lines()->create(['position' => 1, 'description' => 'Servicio de prueba', 'is_selected' => true, 'quantity' => 1, 'unit_price' => 100, 'tax_percent' => 21, 'subtotal' => 100, 'tax_amount' => 21, 'total' => 121]);
        $names = ['journey-'.bin2hex(random_bytes(6)), 'journey-'.bin2hex(random_bytes(6))];
        $workers = [];
        DB::beginTransaction();
        try {
            Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            foreach ($names as $name) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/journey-concurrency-worker.php'), $name, $operation, (string) $user->id, (string) $quote->id], base_path(), timeout: 25);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 10;
            do {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::table('pg_stat_activity')->whereIn('application_name', $names)->where('wait_event_type', 'Lock')->count();
                if ($waiting === 2) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Both independent connections must be waiting before releasing the quote.');
            DB::commit();
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return [$quote->fresh(), $results];
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
    }

    public function test_two_project_requests_share_one_project_and_do_not_create_a_survey(): void
    {
        [$quote, $results] = $this->race('project');
        $this->assertSame($results[0]['project_id'], $results[1]['project_id']);
        $this->assertNotNull($quote->installation_id);
        $this->assertDatabaseCount('installations', 1);
        $this->assertDatabaseCount('work_orders', 0);
    }

    public function test_two_paper_acceptances_preserve_one_proof_history_and_one_unissued_milestone(): void
    {
        [$quote] = $this->race('paper');
        $this->assertSame('accepted', $quote->status);
        $this->assertSame(1, $quote->statusHistory()->where('channel', 'paper')->count());
        $this->assertDatabaseCount('sales_documents', 1);
        $this->assertDatabaseHas('sales_documents', ['quote_id' => $quote->id, 'status' => 'draft', 'issued_at' => null]);
    }
}
