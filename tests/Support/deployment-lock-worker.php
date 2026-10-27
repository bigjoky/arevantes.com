<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::connection()->getDatabaseName() !== 'arevantes_deploy01_incremental_test') {
    throw new RuntimeException('Synthetic incremental database only.');
}
DB::statement("SET application_name='deploy02_synthetic_ddl_lock'");
DB::beginTransaction();
DB::statement('LOCK TABLE document_deliveries IN ACCESS SHARE MODE');
echo "ready\n";
flush();
DB::select('SELECT pg_sleep(4)');
DB::rollBack();
