<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BankReconciliationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_schema_migration_can_be_safely_reentered(): void
    {
        $migration = require database_path('migrations/2026_08_10_120000_create_bank_reconciliation_rules.php');

        $migration->up();

        $this->assertTrue(Schema::hasIndex(
            'bank_reconciliation_rules',
            ['bank_account_id', 'is_active', 'priority'],
        ));
        $this->assertTrue(Schema::hasColumn('bank_transaction_postings', 'reconciliation_rule_id'));
        $this->assertTrue(Schema::hasColumn('bank_transaction_postings', 'reconciliation_run_id'));
        $this->assertTrue(Schema::hasTable('bank_reconciliation_runs'));
        $this->assertTrue(Schema::hasTable('bank_reconciliation_run_items'));

        foreach (['bank_reconciliation_rules', 'bank_reconciliation_runs', 'bank_reconciliation_run_items'] as $table) {
            foreach ([...Schema::getIndexes($table), ...Schema::getForeignKeys($table)] as $identifier) {
                if ($identifier['name'] !== null) {
                    $this->assertLessThanOrEqual(64, strlen($identifier['name']), $identifier['name']);
                }
            }
        }
    }
}
