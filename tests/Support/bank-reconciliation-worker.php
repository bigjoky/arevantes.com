<?php

use App\Models\BankTransaction;
use App\Models\SalesDocument;
use App\Models\User;
use App\Services\Accounting\CustomerPaymentCancellation;
use App\Services\Banking\ControlledReconciliation;
use Illuminate\Contracts\Console\Kernel;
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
$x = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$dir = $x['directory'];
if (! str_starts_with($dir, sys_get_temp_dir().'/arevantes-bank-race-')) {
    exit(92);
}
$user = User::findOrFail($x['user_id']);
Auth::guard('web')->setUser($user);
file_put_contents($dir.'/ready-'.getmypid(), 'ready');
$deadline = microtime(true) + 10;
while (! is_file($dir.'/go') && microtime(true) < $deadline) {
    usleep(10000);
}
if (! is_file($dir.'/go')) {
    exit(93);
}
try {
    if ($x['mode'] === 'cancel') {
        app(CustomerPaymentCancellation::class)->cancel(SalesDocument::findOrFail($x['document_id']), $x['payment_id'], $user, 'Synthetic cancellation', $x['key'], $x['payment_revision']);
    } else {
        app(ControlledReconciliation::class)->match(BankTransaction::findOrFail($x['bank_id']), 'customer:'.$x['payment_id'], $x['amount'], $x['account_id'], $x['revision'], $x['key'], 'Synthetic reconciliation', $user);
    }
    file_put_contents($dir.'/result-'.getmypid(), 'ok');
} catch (ValidationException|HttpException $e) {
    file_put_contents($dir.'/result-'.getmypid(), 'blocked');
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage());
    exit(70);
}
