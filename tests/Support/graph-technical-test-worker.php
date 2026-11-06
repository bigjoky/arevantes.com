<?php

use App\Models\User;
use App\Services\Website\Graph\GraphTechnicalTest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::getDriverName() !== 'pgsql' || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_contact10_test')) { exit(90); }
config(['contact-mail.transport' => 'graph', 'contact-mail.from' => 'info@arevantes.com', 'contact-mail.reply_to' => 'info@arevantes.com',
    'contact-mail.graph.tenant_id' => '7c831860-91be-46c8-9e16-afea4d7b12fa', 'contact-mail.graph.client_id' => '7dbadeb2-e9ec-49dc-ae83-68a9563c236d',
    'contact-mail.graph.sha1_thumbprint' => getenv('TEST_GRAPH_FINGERPRINT'), 'cache.default' => 'array']);
Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'synthetic', 'expires_in' => 3600]), 'graph.microsoft.com/*' => Http::response('', 202)]);
echo 'ready:'.DB::selectOne('select pg_backend_pid() as pid')->pid."\n";
flush();
try {
    app(GraphTechnicalTest::class)->execute(User::findOrFail((int) $argv[2]), $argv[1]);
    echo 'unexpected-send';
} catch (ValidationException) {
    echo 'blocked';
}
