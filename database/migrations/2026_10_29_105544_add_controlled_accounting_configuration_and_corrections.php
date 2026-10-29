<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';
        foreach (['sales_documents', 'purchase_documents', 'expenses', 'sales_document_payments', 'supplier_payments'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->json('accounting_overrides')->nullable();
            });
        }
        foreach (['sales_documents', 'purchase_documents'] as $table) {
            if ($sqlite) {
                // Native nullable ADD COLUMN preserves every existing trigger/view and foreign key.
                DB::statement("ALTER TABLE $table ADD COLUMN accounting_category_id INTEGER NULL REFERENCES financial_categories(id) ON DELETE RESTRICT");
                Schema::table($table, fn (Blueprint $t) => $t->index('accounting_category_id'));
            } else {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('accounting_category_id')->nullable()->constrained('financial_categories')->restrictOnDelete();
                    $t->index('accounting_category_id');
                });
            }
        }
        Schema::create('accounting_configurations', function (Blueprint $t) {
            $t->id();
            $t->json('defaults');
            $t->json('rules');
            $t->json('templates');
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('accounting_account_assignments', function (Blueprint $t) {
            $t->id();
            $t->string('entity_type', 64);
            $t->unsignedBigInteger('entity_id');
            $t->string('role', 64);
            $t->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['entity_type', 'entity_id', 'role'], 'accounting_entity_role_unique');
        });
        Schema::create('accounting_entry_corrections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('operation_key')->unique();
            $t->string('revision', 64);
            $t->date('correction_date');
            $t->text('reason');
            $t->text('notes')->nullable();
            $t->string('reference')->nullable();
            $t->string('description')->nullable();
            $t->json('line_changes');
            $t->foreignId('correction_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $t->timestampTz('recorded_at_utc');
            $t->index(['journal_entry_id', 'id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE accounting_configurations ADD CONSTRAINT accounting_configuration_singleton CHECK (id=1)');
            foreach (['accounting_configurations', 'accounting_account_assignments', 'accounting_entry_corrections'] as $table) {
                DB::statement("ALTER TABLE $table ENABLE ROW LEVEL SECURITY");
                // Laravel uses its trusted server role; no browser/Data API policy is added.
                DB::statement("REVOKE ALL ON TABLE $table FROM PUBLIC");
                DB::statement("REVOKE ALL ON SEQUENCE {$table}_id_seq FROM PUBLIC");
                foreach (['anon', 'authenticated'] as $role) {
                    if (DB::selectOne('select 1 from pg_roles where rolname = ?', [$role])) {
                        DB::statement("REVOKE ALL ON TABLE $table FROM $role");
                        DB::statement("REVOKE ALL ON SEQUENCE {$table}_id_seq FROM $role");
                    }
                }
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Accounting decisions and corrections must be preserved; use a reviewed forward migration.');
    }
};
