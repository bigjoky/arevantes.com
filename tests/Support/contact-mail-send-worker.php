<?php

use App\Services\Website\WebsiteContactOutbox;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::getDriverName() !== 'pgsql' || config('database.connections.pgsql.host') !== '127.0.0.1' || ! str_ends_with(DB::connection()->getDatabaseName(), '_contact10_test')) {
    exit(90);
}
config(['contact-mail.enabled' => true, 'contact-mail.transport' => 'smtp', 'mail.default' => 'smtp']);
Mail::fake();
echo 'ready:'.DB::selectOne('select pg_backend_pid() as pid')->pid."\n";
flush();
echo app(WebsiteContactOutbox::class)->send((int) $argv[1]);
