<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\PlanningCalibrationEvaluation;
use App\Models\PlanningCalibrationGovernanceReview;
use App\Models\PlanningCalibrationProposal;
use App\Models\PlanningScenario;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TemporalConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_temporal_concurrency_test')) {
            $this->markTestSkipped('Requires a disposable local PostgreSQL temporal concurrency database.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    }

    private function race(string $operation, User $actor, int $parentId, callable $hold): void
    {
        $workers = [];
        $names = ['temporal-'.bin2hex(random_bytes(6)), 'temporal-'.bin2hex(random_bytes(6))];
        DB::beginTransaction();
        try {
            $hold();
            foreach ($names as $name) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/temporal-concurrency-worker.php'), $name, $operation, (string) $actor->id, (string) $parentId], base_path(), timeout: 25);
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
            $this->assertSame(2, $waiting, 'Both independent writers must be blocked before release. '.implode(' ', array_map(fn ($worker) => $worker->getErrorOutput().$worker->getOutput(), $workers)));
            DB::commit();
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            }
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

    private function proposal(User $actor): PlanningCalibrationProposal
    {
        return PlanningCalibrationProposal::create(['business_line' => 'solar', 'area' => 'labour', 'metric_label' => 'Hours', 'metric_key' => hash('sha256', 'metric'), 'unit' => 'hours', 'version' => 1, 'status' => 'accepted', 'direction' => 'higher', 'sample_size' => 3, 'evidence_snapshot' => ['average_delta_percent' => 50], 'source_fingerprint' => hash('sha256', 'origin'), 'recommendation' => 'Synthetic', 'created_by' => $actor->id]);
    }

    private function scenario(User $actor, PlanningCalibrationProposal $proposal, int $version): PlanningScenario
    {
        return PlanningScenario::create(['name' => 'Synthetic', 'version' => $version, 'status' => 'closed', 'horizon_starts_on' => '2026-10-25', 'horizon_ends_on' => '2026-10-26', 'assumptions' => ['calibration_reference' => ['proposal_id' => $proposal->id, 'source_fingerprint' => $proposal->source_fingerprint, 'assumption_key' => 'labour', 'previous_value' => 1, 'applied_value' => 1.5]], 'opportunity_snapshot' => [], 'result_snapshot' => [], 'closure_snapshot' => ['areas' => [['area' => 'labour', 'comparison' => [['label' => 'Hours', 'unit' => 'hours', 'planned' => 10, 'current' => 11]]]]], 'closure_fingerprint' => hash('sha256', 'closure-'.$version), 'fingerprint' => hash('sha256', 'scenario-'.$version), 'created_by' => $actor->id]);
    }

    public function test_empty_document_series_allocates_distinct_versions_under_concurrent_uploads(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $project = Installation::create(['service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Synthetic']);
        $order = WorkOrder::create(['installation_id' => $project->id, 'number' => 'WO-SYNTHETIC', 'title' => 'Synthetic', 'kind' => 'installation', 'status' => 'planned', 'required_evidence' => [['key' => 'legalization', 'kind' => 'document']]]);
        $this->race('document', $actor, $order->id, fn () => Installation::whereKey($project->id)->lockForUpdate()->firstOrFail());
        $this->assertSame([1, 2], WorkOrderAttachment::orderBy('document_version')->pluck('document_version')->all());
    }

    public function test_empty_calibration_series_and_duplicate_evidence_are_serialized(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $metric = hash('sha256', json_encode(['labour', 'Hours', 'hours'], JSON_THROW_ON_ERROR));
        $this->race('proposal', $actor, 0, fn () => DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['calibration:solar:'.$metric]));
        $this->assertSame([1], PlanningCalibrationProposal::pluck('version')->all());
    }

    public function test_duplicate_scenario_evaluations_are_serialized_on_the_proposal(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $proposal = $this->proposal($actor);
        $this->scenario($actor, $proposal, 1);
        $this->race('evaluation', $actor, $proposal->id, fn () => PlanningCalibrationProposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail());
        $this->assertSame([1], PlanningCalibrationEvaluation::pluck('version')->all());
    }

    public function test_duplicate_governance_reviews_are_serialized_on_the_proposal(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $proposal = $this->proposal($actor);
        foreach (range(1, 3) as $version) {
            $scenario = $this->scenario($actor, $proposal, $version);
            PlanningCalibrationEvaluation::create(['planning_calibration_proposal_id' => $proposal->id, 'planning_scenario_id' => $scenario->id, 'version' => $version, 'status' => 'decided', 'decision' => 'retain', 'recommendation' => 'retain', 'origin_delta_percent' => 50, 'observed_delta_percent' => 10, 'improvement_points' => 40, 'evidence_snapshot' => [], 'fingerprint' => hash('sha256', 'evaluation-'.$version), 'created_by' => $actor->id]);
        }
        $this->race('governance', $actor, $proposal->id, fn () => PlanningCalibrationProposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail());
        $this->assertSame([1], PlanningCalibrationGovernanceReview::pluck('version')->all());
    }
}
