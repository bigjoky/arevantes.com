<?php

use App\Models\Expense;
use App\Models\PurchaseDocument;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'pgsql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_eco02_concurrency_test')) {
    throw new RuntimeException('Disposable ECO-02 PostgreSQL database required.');
}
$data = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
Auth::login(User::findOrFail($data['actor']));
DB::statement("SET statement_timeout = '20s'");
file_put_contents($data['directory'].'/'.getmypid().'.ready', 'ready');
$deadline = microtime(true) + 8;
while (count(glob($data['directory'].'/*.ready')) < 2) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrency barrier timed out.');
    }
    usleep(10000);
}
try {
    $result = DB::transaction(function () use ($data): array {
        if ($data['operation'] === 'post') {
            $source = PurchaseDocument::findOrFail($data['id']);
        } elseif ($data['operation'] === 'purchase') {
            $source = PurchaseDocument::create($data['attributes']);
        } else {
            $source = Expense::create($data['attributes']);
        }
        $posted = $source instanceof Expense ? app(AccountingSynchronizer::class)->syncExpense($source) : app(AccountingSynchronizer::class)->syncPurchaseDocument($source);

        return ['status' => 'ok', 'source_id' => $source->id, 'posted' => $posted];
    });
} catch (QueryException $exception) {
    if (($exception->errorInfo[0] ?? '') !== '23505') {
        throw $exception;
    }
    $result = ['status' => 'duplicate'];
}
echo json_encode($result, JSON_THROW_ON_ERROR);
