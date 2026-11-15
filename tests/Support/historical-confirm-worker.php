<?php

use App\Models\FiscalYear;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodManager;
use App\Services\Accounting\FiscalYearClosing;
use App\Services\HistoricalInvoices\ConfirmInvoice;
use App\Services\Tax\VatSettlement;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'pgsql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_reg2026_concurrency_test')) {
    throw new RuntimeException('Disposable REG-2026 database required.');
}
$data = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
config(['fiscal.historical_invoice_2026' => true]);
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
    $result = match ($data['operation'] ?? 'confirm') {
        'confirm' => app(ConfirmInvoice::class)->confirm($data['row'], User::findOrFail($data['user']), '2026-03-15')->id,
        'close_month' => app(AccountingPeriodManager::class)->close(2026, 3, $data['user'], ['reviewed' => true], null)->id,
        'close_year' => (function () use ($data) {
            Carbon::setTestNow('2027-02-01');
            app(FiscalYearClosing::class)->close(FiscalYear::where('year', 2026)->firstOrFail(), $data['user']);

            return 'closed';
        })(),
        'post_vat' => app(VatSettlement::class)->post(TaxReturn::findOrFail($data['return']), $data['user'])->id,
        'prepare_vat' => app(VatSettlement::class)->prepare(2026, 1, $data['user'])->id,
        default => throw new RuntimeException('Unknown operation.'),
    };
    echo json_encode(['status' => 'ok', 'result' => $result], JSON_THROW_ON_ERROR);
} catch (ValidationException|HttpException $exception) {
    echo json_encode(['status' => 'rejected'], JSON_THROW_ON_ERROR);
}
