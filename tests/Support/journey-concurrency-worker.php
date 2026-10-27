<?php

use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuotePaperAcceptanceController;
use App\Jobs\GeocodeInstallation;
use App\Models\Quote;
use App\Models\User;
use App\Services\Quotes\QuoteStatusWorkflow;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'pgsql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_journey_concurrency_test')) {
    throw new RuntimeException('Only the disposable local journey concurrency database is allowed.');
}
DB::statement("SET statement_timeout = '15s'");
DB::select('SELECT set_config(?, ?, false)', ['application_name', $argv[1]]);
Bus::fake([GeocodeInstallation::class]);
Auth::loginUsingId((int) $argv[3]);
$quote = Quote::findOrFail((int) $argv[4]);
if ($argv[2] === 'project') {
    $request = Request::create('/quotes/'.$quote->id.'/project', 'POST', ['service_type' => 'solar', 'site_address_line_1' => 'Prueba de concurrencia', 'site_country' => 'Spain']);
    $request->setUserResolver(fn () => User::findOrFail((int) $argv[3]));
    app()->call([app(QuoteController::class), 'startProject'], ['request' => $request, 'quote' => $quote]);
} else {
    $request = Request::create('/quotes/'.$quote->id.'/paper-acceptance', 'POST', ['accepted_at' => today()->toDateString(), 'signed_by_name' => 'Firma ficticia', 'reason' => 'Prueba de concurrencia'], [], ['proof' => UploadedFile::fake()->image('signature.jpg')]);
    $request->setUserResolver(fn () => User::findOrFail((int) $argv[3]));
    config(['filesystems.disks.local.root' => sys_get_temp_dir().'/arevantes-journey-concurrency-files']);
    app(QuotePaperAcceptanceController::class)->store($request, $quote, app(QuoteStatusWorkflow::class));
}
echo json_encode(['status' => 'ok', 'project_id' => $quote->fresh()->installation_id]);
