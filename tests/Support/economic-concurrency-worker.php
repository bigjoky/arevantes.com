<?php

use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\SalesDocumentController;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Documents\SalesDocumentConversion;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'pgsql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_economic_concurrency_test')) {
    throw new RuntimeException('Disposable local economic concurrency database required.');
}
DB::statement("SET statement_timeout = '18s'");
DB::select('SELECT set_config(?, ?, false)', ['application_name', $argv[1]]);
$data = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
$actor = User::findOrFail($data['user_id']);
Auth::login($actor);
config(['filesystems.disks.local.root' => $data['directory'].'/files']);
if (in_array($data['operation'], ['convert', 'payment_key_race', 'supplier_key_race'], true)) {
    $paused = false;
    DB::listen(function ($query) use ($data, &$paused): void {
        $capacityRead = $data['operation'] === 'convert' && str_contains($query->sql, 'sum(invoice_lines.quantity)');
        $keyRead = in_array($data['operation'], ['payment_key_race', 'supplier_key_race'], true) && str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, $data['operation'] === 'supplier_key_race' ? 'supplier_payments' : 'sales_document_payments') && str_contains($query->sql, 'idempotency_key');
        if ($paused || (! $capacityRead && ! $keyRead)) {
            return;
        }
        $paused = true;
        file_put_contents($data['directory'].'/'.getmypid().'.ready', 'ready');
        $deadline = microtime(true) + 4;
        while (count(glob($data['directory'].'/*.ready')) < 2 && microtime(true) < $deadline) {
            usleep(20000);
        }
    });
}
try {
    $document = str_starts_with($data['operation'], 'supplier_') ? PurchaseDocument::findOrFail($data['document_id']) : SalesDocument::findOrFail($data['document_id']);
    $request = Request::create('/synthetic-economic-test', 'POST', $data['payload'] ?? []);
    $request->setUserResolver(fn () => $actor);
    $controller = app(SalesDocumentController::class);
    match ($data['operation']) {
        'convert' => app(SalesDocumentConversion::class)->convert($document, 'invoice', [$data['line_id'] => 10], $actor->id),
        'issue' => $controller->issue($request, $document, app(AccountingSynchronizer::class)),
        'payment', 'payment_key_race' => $controller->storePayment($request, $document, app(AccountingSynchronizer::class)),
        'supplier_key_race' => app(ExpenseController::class)->paySupplierInvoice($request, $document, app(AccountingSynchronizer::class)),
        'supplier_delete' => app(ExpenseController::class)->destroyPayment(SupplierPayment::findOrFail($data['receipt_id'])),
        'supplier_post' => app(AccountingSynchronizer::class)->syncSupplierPayment(SupplierPayment::findOrFail($data['receipt_id']), $actor->id),
        'duplicate' => $controller->duplicate($request, $document),
        'credit' => $controller->creditNote($request, $document),
        'delete_receipt' => $controller->destroyPayment($document, SalesDocumentPayment::findOrFail($data['receipt_id'])),
        'post_receipt' => app(AccountingSynchronizer::class)->syncCustomerPayment(SalesDocumentPayment::findOrFail($data['receipt_id']), $actor->id),
    };
    echo json_encode(['outcome' => 'applied']);
} catch (ValidationException $exception) {
    echo json_encode(['outcome' => 'rejected', 'status' => 422]);
} catch (HttpException $exception) {
    echo json_encode(['outcome' => 'rejected', 'status' => $exception->getStatusCode()]);
} catch (ModelNotFoundException $exception) {
    echo json_encode(['outcome' => 'rejected', 'status' => 404]);
}
