<?php

namespace Tests\Feature;

use App\Models\BackupRestoration;
use App\Models\DatabaseBackup;
use App\Services\Deye\DeyeCloudClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StabilizationHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_deye_configuration_requires_exactly_the_clients_four_fields(): void
    {
        config(['services.deye.app_id' => 'synthetic-id', 'services.deye.app_secret' => 'synthetic-secret', 'services.deye.email' => 'synthetic@example.test', 'services.deye.password' => 'synthetic-password']);
        $this->assertTrue(app(DeyeCloudClient::class)->configured());
        foreach (['app_id', 'app_secret', 'email', 'password'] as $field) {
            $old = config('services.deye.'.$field);
            config(['services.deye.'.$field => null]);
            $this->assertFalse(app(DeyeCloudClient::class)->configured());
            config(['services.deye.'.$field => $old]);
        }
    }

    public function test_valid_deye_credentials_do_not_hide_a_failed_postgres_recovery(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL backup/restore health is only applicable on PostgreSQL.');
        }
        Http::fake(['http://localhost:11434/api/tags' => Http::response(['models' => []])]);
        Cache::put('system.scheduler.heartbeat', now());
        config(['services.deye.app_id' => 'synthetic-id', 'services.deye.app_secret' => 'synthetic-secret', 'services.deye.email' => 'synthetic@example.test', 'services.deye.password' => 'synthetic-password', 'backup.postgres.enabled' => true, 'backup.postgres.restore_enabled' => true]);
        $backup = DatabaseBackup::create(['engine' => 'pgsql', 'status' => 'success', 'disk' => 'local', 'path' => 'synthetic-health-only', 'started_at' => now(), 'completed_at' => now()]);
        BackupRestoration::create(['database_backup_id' => $backup->id, 'backup_name' => 'synthetic', 'engine' => 'pgsql', 'status' => 'healthy', 'verified_at' => now()->subMinute()]);
        BackupRestoration::create(['database_backup_id' => $backup->id, 'backup_name' => 'synthetic', 'engine' => 'pgsql', 'status' => 'failed', 'verified_at' => now()]);
        $this->artisan('system:health-check')->assertFailed();
        $this->assertDatabaseHas('system_health_checks', ['component' => 'integration:deye', 'status' => 'healthy']);
        $this->assertDatabaseHas('system_health_checks', ['component' => 'database-restore', 'status' => 'failed']);
    }
}
