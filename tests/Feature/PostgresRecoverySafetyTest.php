<?php

namespace Tests\Feature;

use App\Models\BackupRestoration;
use App\Models\DatabaseBackup;
use App\Models\PurchaseDocument;
use App\Services\Backup\PostgresConnection;
use App\Services\Backup\PostgresRestoreVerifier;
use App\Services\Backup\RecoveryDuration;
use App\Services\Documents\PurchaseOrderEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class PostgresRecoverySafetyTest extends TestCase
{
    use RefreshDatabase;

    public static function unsafeTargets(): array
    {
        return [
            'same name with another hostname' => ['app_source', 'isolated.invalid', 5432, true],
            'same name another port' => ['app_source', 'source.invalid', 6432, true],
            'demo falsely named disposable' => ['app_demo_restore_verify_test', 'isolated.invalid', 5432, true],
            'broad pattern cannot authorize business' => ['erp_business', 'isolated.invalid', 5432, true],
            'production word forbidden' => ['production_restore_verify_test', 'isolated.invalid', 5432, true],
            'system database forbidden' => ['postgres', 'isolated.invalid', 5432, true],
            'conninfo cannot override target' => ['host=source.invalid dbname=app_source', 'isolated.invalid', 5432, true],
            'same host requires explicit isolation setting' => ['app_restore_verify_test', 'source.invalid', 5432, false],
        ];
    }

    #[DataProvider('unsafeTargets')]
    public function test_unsafe_destinations_are_rejected_before_connecting_or_running_commands(string $name, string $host, int $port, bool $sameHost): void
    {
        config(['backup.postgres.restore_enabled' => true, 'backup.postgres.restore_database_pattern' => '/.*/', 'backup.postgres.allow_same_host_restore' => $sameHost,
            'backup.postgres.source_connection' => 'safety_source', 'backup.postgres.restore_connection' => 'safety_target',
            'database.connections.safety_source' => ['driver' => 'pgsql', 'host' => 'source.invalid', 'port' => 5432, 'database' => 'app_source', 'username' => 'synthetic', 'password' => 'not-a-real-secret'],
            'database.connections.safety_target' => ['driver' => 'pgsql', 'host' => $host, 'port' => $port, 'database' => $name, 'username' => 'synthetic', 'password' => 'not-a-real-secret'],
            'backup.postgres.psql_binary' => '/never-run-stab03', 'backup.postgres.pg_restore_binary' => '/never-run-stab03']);
        $backup = DatabaseBackup::create(['engine' => 'pgsql', 'status' => 'success', 'disk' => 'local', 'path' => 'does-not-exist', 'started_at' => now(), 'completed_at' => now()]);
        try {
            app(PostgresRestoreVerifier::class)->verify($backup);
            $this->fail('Unsafe target must never be used.');
        } catch (\RuntimeException $exception) {
            $this->assertStringNotContainsString('never-run-stab03', $exception->getMessage());
            $this->assertStringNotContainsString('not-a-real-secret', $exception->getMessage());
            $this->assertStringNotContainsString('could not translate host', $exception->getMessage());
        }
        $record = BackupRestoration::latest('id')->firstOrFail();
        $this->assertSame('failed', $record->status);
        $this->assertIsInt($record->rpo_seconds);
        $this->assertIsInt($record->rto_seconds);
        $this->assertFalse($record->metrics['target_cleanup_failed']);
    }

    public function test_url_options_cannot_override_explicit_connection_identity(): void
    {
        config(['database.connections.unsafe_url' => ['driver' => 'pgsql', 'url' => 'postgresql://synthetic:fake@127.0.0.1/app_restore_verify_test?database=app_source']]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported PostgreSQL URL options');
        app(PostgresConnection::class)->parameters('unsafe_url');
    }

    public function test_command_environment_cannot_inherit_libpq_routing_overrides(): void
    {
        $env = app(PostgresConnection::class)->processEnvironment(['password' => 'synthetic', 'sslmode' => 'disable']);
        foreach (['PGHOSTADDR', 'PGSERVICE', 'PGSERVICEFILE', 'PGOPTIONS', 'PGDATABASE'] as $key) {
            $this->assertFalse($env[$key]);
        }
    }

    public function test_duration_contract_retains_microseconds_without_float_or_truncation(): void
    {
        $from = CarbonImmutable::parse('2026-10-25T00:30:00.000001Z');
        $to = CarbonImmutable::parse('2026-10-25T01:30:00.000002Z');
        $micros = RecoveryDuration::microseconds($from, $to);
        $this->assertSame(3600000001, $micros);
        $this->assertSame(3601, RecoveryDuration::seconds($micros));
        $this->assertSame(1, RecoveryDuration::seconds(1));
        $this->assertSame(1, RecoveryDuration::seconds(1000000));
        $this->assertSame(0, RecoveryDuration::seconds(0));
    }

    public function test_unreachable_isolated_destination_fails_without_invoking_restore(): void
    {
        config(['backup.postgres.restore_enabled' => true, 'backup.postgres.allow_same_host_restore' => true, 'backup.postgres.source_connection' => 'unreachable_source', 'backup.postgres.restore_connection' => 'unreachable_target',
            'database.connections.unreachable_source' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 5432, 'database' => 'synthetic_source', 'username' => 'synthetic'],
            'database.connections.unreachable_target' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'synthetic_restore_verify_test', 'username' => 'synthetic', 'password' => 'do-not-leak-this'],
            'backup.postgres.psql_binary' => '/never-run-stab03']);
        $backup = DatabaseBackup::create(['engine' => 'pgsql', 'status' => 'success', 'disk' => 'local', 'path' => 'missing', 'started_at' => now(), 'completed_at' => now()]);
        try {
            app(PostgresRestoreVerifier::class)->verify($backup);
            $this->fail('Unreachable target must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringNotContainsString('do-not-leak-this', $exception->getMessage());
            $this->assertStringNotContainsString('never-run-stab03', $exception->getMessage());
        }
        $this->assertSame('failed', BackupRestoration::latest('id')->value('status'));
    }

    public function test_exact_decimal_snapshot_is_stable_before_and_after_database_roundtrip(): void
    {
        $supplier = EconomicFixture::supplier(['legal_name' => 'Synthetic decimal fixture', 'country' => 'ES']);
        $order = PurchaseDocument::create(['type' => 'purchase_order', 'supplier_id' => $supplier->id, 'number' => 'STAB-DECIMAL', 'status' => 'ordered', 'issue_date' => today(), 'subtotal' => '1234567890.10', 'tax_amount' => '0.00', 'amount' => '1234567890.10']);
        $before = app(PurchaseOrderEditor::class)->snapshot($order);
        $after = app(PurchaseOrderEditor::class)->snapshot($order->fresh());
        $this->assertSame('1234567890.10', $before['amount']);
        $this->assertSame($before['amount'], $after['amount']);
        $this->assertSame($after['amount'], json_decode(json_encode($after, JSON_THROW_ON_ERROR), true)['amount']);
    }

    public function test_failure_to_record_result_does_not_hide_the_original_safety_rejection(): void
    {
        config(['backup.postgres.restore_enabled' => true, 'backup.postgres.source_connection' => 'log_source', 'backup.postgres.restore_connection' => 'log_target',
            'database.connections.log_source' => ['driver' => 'pgsql', 'host' => 'source.invalid', 'port' => 5432, 'database' => 'same_database', 'username' => 'synthetic'],
            'database.connections.log_target' => ['driver' => 'pgsql', 'host' => 'alias.invalid', 'port' => 5432, 'database' => 'same_database', 'username' => 'synthetic']]);
        $backup = DatabaseBackup::create(['engine' => 'pgsql', 'status' => 'success', 'disk' => 'local', 'path' => 'not-read', 'started_at' => now(), 'completed_at' => now()]);
        BackupRestoration::updating(function ($record): void {
            if ($record->status === 'failed') {
                throw new \RuntimeException('Synthetic metadata write failure');
            }
        });
        try {
            app(PostgresRestoreVerifier::class)->verify($backup);
            $this->fail('The original rejection must be reported.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('production database', $exception->getMessage());
            $this->assertStringContainsString('could not be persisted', $exception->getMessage());
        }
    }

    public function test_missing_or_future_backup_completion_cannot_produce_a_valid_rpo(): void
    {
        foreach ([null, now()->addDay()] as $completion) {
            $backup = DatabaseBackup::create(['engine' => 'pgsql', 'status' => 'success', 'disk' => 'local', 'path' => 'not-read', 'started_at' => now(), 'completed_at' => $completion]);
            try {
                app(PostgresRestoreVerifier::class)->verify($backup);
                $this->fail('Invalid completion time must be rejected.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('completion timestamp', $exception->getMessage());
            }
            $record = BackupRestoration::latest('id')->firstOrFail();
            $this->assertSame('failed', $record->status);
            $this->assertNull($record->rpo_seconds);
        }
    }
}
