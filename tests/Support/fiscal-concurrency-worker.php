<?php

use App\Http\Controllers\SalesDocumentController;
use App\Models\SalesDocument;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Documents\DocumentNumber;
use App\Services\Tax\VatSettlement;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::connection()->getDriverName() !== 'pgsql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_concurrency_test')) {
    throw new RuntimeException('Concurrency workers require an isolated PostgreSQL test database.');
}
DB::statement("SET statement_timeout = '12s'");
DB::select('SELECT set_config(?, ?, false)', ['application_name', $argv[1]]);
$issue = function () use ($argv) {
    $request = Request::create('/sales/'.$argv[4].'/issue', 'POST', ['issue_date' => '2026-09-15']);
    $request->setUserResolver(fn () => User::findOrFail((int) $argv[3]));
    app(SalesDocumentController::class)->issue($request, SalesDocument::findOrFail((int) $argv[4]), app(AccountingSynchronizer::class));

    return SalesDocument::findOrFail((int) $argv[4])->number;
};
try {
    $result = match ($argv[2]) {
        'draft' => app(VatSettlement::class)->prepare(2026, 3, (int) $argv[3])->id,
        'post' => app(VatSettlement::class)->post(TaxReturn::findOrFail((int) $argv[4]), (int) $argv[3])->journal_entry_id,
        'number' => DB::transaction(fn () => app(DocumentNumber::class)->next('expense', '2026-09-15')['number']),
        'issue' => $issue(),
        default => throw new RuntimeException('Unknown operation.'),
    };
    echo json_encode(['status' => 'ok', 'result' => $result], JSON_THROW_ON_ERROR);
} catch (ValidationException $exception) {
    echo json_encode(['status' => 'rejected', 'errors' => array_keys($exception->errors())], JSON_THROW_ON_ERROR);
} catch (HttpException $exception) {
    echo json_encode(['status' => 'rejected', 'http_status' => $exception->getStatusCode()], JSON_THROW_ON_ERROR);
}
