<?php

use App\Http\Controllers\WorkOrderController;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Planning\PlanningCalibrationEvaluationService;
use App\Services\Planning\PlanningCalibrationGovernanceService;
use App\Services\Planning\PlanningCalibrationProposalService;
use App\Services\Work\WorkOrderEvidenceRequirements;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'pgsql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_temporal_concurrency_test')) {
    throw new RuntimeException('Disposable local temporal concurrency database required.');
}
DB::statement("SET statement_timeout = '15s'");
DB::select('SELECT set_config(?, ?, false)', ['application_name', $argv[1]]);
$actor = User::findOrFail((int) $argv[3]);
Auth::login($actor);
$operation = $argv[2];
if ($operation === 'document') {
    $order = WorkOrder::findOrFail((int) $argv[4]);
    $request = Request::create('/synthetic-test', 'POST', ['evidence_key' => 'legalization', 'submit_for_review' => true], [], ['files' => [UploadedFile::fake()->create('synthetic.pdf', 1, 'application/pdf')]]);
    $request->setUserResolver(fn () => $actor);
    config(['filesystems.disks.local.root' => sys_get_temp_dir().'/arevantes-temporal-concurrency-files']);
    app(WorkOrderController::class)->storeAttachments($request, $order, app(WorkOrderEvidenceRequirements::class));
} elseif ($operation === 'proposal') {
    $evidence = collect(range(1, 3))->map(fn ($id) => ['scenario_id' => $id, 'scenario_name' => 'Synthetic', 'scenario_version' => 1, 'closure_fingerprint' => hash('sha256', 'same-evidence-'.$id), 'business_line' => 'solar', 'area' => 'labour', 'label' => 'Hours', 'unit' => 'hours', 'planned' => 10, 'observed' => 15, 'delta' => 5, 'delta_percent' => 50]);
    (new ReflectionMethod(PlanningCalibrationProposalService::class, 'createFrom'))->invoke(app(PlanningCalibrationProposalService::class), $evidence, $actor);
} elseif ($operation === 'evaluation') {
    app(PlanningCalibrationEvaluationService::class)->generate($actor);
} else {
    app(PlanningCalibrationGovernanceService::class)->generate($actor);
}
echo json_encode(['ok' => true]);
