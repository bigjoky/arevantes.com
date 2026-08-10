<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->string('direction', 10)->default('any');
            $table->string('match_field', 20)->default('either');
            $table->string('match_operator', 20)->default('contains');
            $table->string('pattern');
            $table->decimal('min_amount', 14, 2)->nullable();
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->unsignedTinyInteger('confidence')->default(95);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('application_count')->default(0);
            $table->timestamp('last_applied_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['bank_account_id', 'name']);
            $table->index(['bank_account_id', 'is_active', 'priority']);
        });

        Schema::create('bank_reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('running');
            $table->unsignedTinyInteger('minimum_confidence')->default(95);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('bank_transaction_postings', function (Blueprint $table) {
            $table->foreignId('reconciliation_rule_id')->nullable()->after('bank_transaction_id')->constrained('bank_reconciliation_rules')->nullOnDelete();
            $table->foreignId('reconciliation_run_id')->nullable()->after('reconciliation_rule_id')->constrained('bank_reconciliation_runs')->nullOnDelete();
        });

        Schema::create('bank_reconciliation_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_reconciliation_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_transaction_posting_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('confidence');
            $table->string('outcome', 20);
            $table->string('explanation', 500);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['bank_reconciliation_run_id', 'bank_transaction_id'], 'bank_rule_run_transaction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_run_items');
        Schema::table('bank_transaction_postings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reconciliation_rule_id');
            $table->dropConstrainedForeignId('reconciliation_run_id');
        });
        Schema::dropIfExists('bank_reconciliation_runs');
        Schema::dropIfExists('bank_reconciliation_rules');
    }
};
