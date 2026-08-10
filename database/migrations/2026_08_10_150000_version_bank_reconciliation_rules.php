<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_reconciliation_rules', function (Blueprint $table) {
            $table->unsignedInteger('current_version')->default(1)->after('last_applied_at');
        });

        Schema::create('bank_reconciliation_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_reconciliation_rule_id');
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('change_reason', 500);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['bank_reconciliation_rule_id', 'version'], 'bank_rule_version_unique');
            $table->foreign('bank_reconciliation_rule_id', 'bank_rule_version_rule_fk')->references('id')->on('bank_reconciliation_rules')->cascadeOnDelete();
        });

        $now = now();
        DB::table('bank_reconciliation_rules')->orderBy('id')->each(function ($rule) use ($now) {
            DB::table('bank_reconciliation_rule_versions')->insert([
                'bank_reconciliation_rule_id' => $rule->id,
                'version' => 1,
                'snapshot' => json_encode($this->snapshot($rule), JSON_THROW_ON_ERROR),
                'change_reason' => 'Initial version captured during reconciliation rule governance upgrade.',
                'changed_by' => $rule->updated_by ?? $rule->created_by,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        Schema::table('bank_transaction_postings', function (Blueprint $table) {
            $table->unsignedBigInteger('reconciliation_rule_version_id')->nullable()->after('reconciliation_rule_id');
            $table->foreign('reconciliation_rule_version_id', 'bank_posting_rule_version_fk')->references('id')->on('bank_reconciliation_rule_versions')->nullOnDelete();
        });
        Schema::table('bank_reconciliation_run_items', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_reconciliation_rule_version_id')->nullable()->after('bank_reconciliation_rule_id');
            $table->foreign('bank_reconciliation_rule_version_id', 'bank_run_item_rule_version_fk')->references('id')->on('bank_reconciliation_rule_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_reconciliation_run_items', function (Blueprint $table) {
            $table->dropForeign('bank_run_item_rule_version_fk');
            $table->dropColumn('bank_reconciliation_rule_version_id');
        });
        Schema::table('bank_transaction_postings', function (Blueprint $table) {
            $table->dropForeign('bank_posting_rule_version_fk');
            $table->dropColumn('reconciliation_rule_version_id');
        });
        Schema::dropIfExists('bank_reconciliation_rule_versions');
        Schema::table('bank_reconciliation_rules', function (Blueprint $table) {
            $table->dropColumn('current_version');
        });
    }

    private function snapshot(object $rule): array
    {
        return collect((array) $rule)->only([
            'name', 'ledger_account_id', 'priority', 'direction', 'match_field',
            'match_operator', 'pattern', 'min_amount', 'max_amount', 'confidence', 'is_active',
        ])->all();
    }
};
