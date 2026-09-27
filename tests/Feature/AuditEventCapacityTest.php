<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditEventCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollback_refuses_to_truncate_existing_audit_events(): void
    {
        $log = AuditLog::create([
            'event' => 'expense_issuer_corrected',
            'auditable_type' => 'regression-test',
            'auditable_id' => 0,
        ]);

        $migration = require database_path('migrations/2026_09_27_010000_expand_audit_event_names.php');
        try {
            $migration->down();
            $this->fail('Rollback must not truncate audit events.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Cannot shorten audit event names while longer events exist.', $exception->getMessage());
        }

        $this->assertSame('expense_issuer_corrected', $log->fresh()->event);
    }

    public function test_audit_event_capacity_covers_expense_corrections_on_postgresql(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL enforces varchar lengths; SQLite does not.');
        }

        $column = collect(Schema::getColumns('audit_logs'))->firstWhere('name', 'event');
        $this->assertSame('character varying(255)', $column['type']);

        $log = AuditLog::create([
            'event' => 'expense_issuer_corrected',
            'auditable_type' => 'regression-test',
            'auditable_id' => 0,
            'old_values' => ['issuer_legal_name' => 'Before'],
            'new_values' => ['issuer_legal_name' => 'After'],
        ]);

        $this->assertSame('expense_issuer_corrected', $log->fresh()->event);
    }
}
