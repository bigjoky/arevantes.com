<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local') || DB::connection()->getDatabaseName() !== 'arevantes_deploy02_qa_test') {
    throw new RuntimeException('Synthetic QA only.');
}
if (config('database.connections.pgsql.host') !== '127.0.0.1' || filled(config('database.connections.pgsql.url')) || DB::selectOne("SELECT shobj_description(oid,'pg_database') AS marker FROM pg_database WHERE datname=current_database()")->marker !== 'arevantes:deploy02:synthetic:v1') {
    throw new RuntimeException('Exclusive local QA marker and endpoint required.');
}
$connection = DB::selectOne("SELECT current_database() AS database, current_user AS role, current_setting('TimeZone') AS timezone");
$privileges = DB::selectOne('SELECT rolsuper,rolcreatedb,rolcreaterole FROM pg_roles WHERE rolname=current_user');
$configured = [];
foreach (['services.deye.app_id', 'services.deye.app_secret', 'services.deye.email', 'services.deye.password', 'services.huawei.username', 'services.huawei.system_code', 'services.apns.private_key', 'services.postmark.key', 'services.resend.key', 'services.ses.key', 'services.ses.secret'] as $key) {
    $configured[$key] = filled(config($key));
}
if (array_filter($configured) || config('mail.default') !== 'log' || config('queue.default') !== 'database' || config('filesystems.private') !== 'local' || $connection->role !== 'deploy02_qa' || $privileges->rolsuper) {
    throw new RuntimeException('QA isolation check rejected active integrations or unexpected resources.');
}
echo json_encode(['url' => config('app.url'), 'environment' => app()->environment(), 'connection' => $connection, 'role_privileges' => $privileges, 'document_disk' => config('filesystems.private'), 'storage_root' => storage_path(), 'session_driver' => config('session.driver'), 'session_cookie' => config('session.cookie'), 'cache_store' => config('cache.default'), 'cache_prefix' => config('cache.prefix'), 'queue' => config('queue.default'), 'queue_name' => config('queue.connections.database.queue'), 'mail' => config('mail.default'), 'backup_enabled' => config('backup.postgres.enabled'), 'restore_enabled' => config('backup.postgres.restore_enabled'), 'integrations_configured' => $configured, 'app_key_sha256' => hash('sha256', config('app.key'))], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),PHP_EOL;
