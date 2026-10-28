<?php

use App\Http\Controllers\SalesDocumentController;
use App\Models\SalesDocument;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\CustomerPaymentCancellation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('testing') || DB::getDriverName() !== 'pgsql' || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_ops01_test')) {
    exit(90);
}
if (DB::selectOne("SELECT shobj_description(oid,'pg_database') AS marker FROM pg_database WHERE datname=current_database()")->marker !== 'arevantes:ops01:synthetic-disposable:v1') {
    exit(91);
}
$data = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$dir = $data['directory'];
if (! str_starts_with($dir, sys_get_temp_dir().'/arevantes-receipt-race-')) {
    exit(92);
}
file_put_contents($dir.'/ready-'.getmypid(), 'ready');
$deadline = microtime(true) + 10;
while (! is_file($dir.'/go') && microtime(true) < $deadline) {
    usleep(10000);
}
if (! is_file($dir.'/go')) {
    exit(93);
}
$user = User::findOrFail($data['user_id']);
Auth::guard('web')->setUser($user);
$document = SalesDocument::findOrFail($data['document_id']);
try {
    if ($data['mode'] === 'cancel') {
        app(CustomerPaymentCancellation::class)->cancel($document, $data['payment_id'], $user, 'Synthetic incorrect receipt', $data['key'], $data['revision']);
    } else {
        $request = Request::create('/sales/'.$document->id.'/payments', 'POST', ['idempotency_key' => $data['key'], 'paid_at' => '2026-09-22', 'amount' => '121.00', 'method' => 'cash']);
        $request->setUserResolver(fn () => $user);
        app()->instance('request', $request);
        app(SalesDocumentController::class)->storePayment($request, $document, app(AccountingSynchronizer::class));
    }
    file_put_contents($dir.'/result-'.getmypid(), 'ok');
} catch (ValidationException|HttpException $e) {
    file_put_contents($dir.'/result-'.getmypid(), 'blocked');
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage());
    exit(70);
}
