<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('bank_statement_import_id')->nullable()->after('bank_account_id')->constrained('bank_statement_imports')->nullOnDelete();
            $table->index(['bank_statement_import_id', 'status']);
        });

        Schema::table('bank_statement_imports', function (Blueprint $table) {
            $table->foreignId('rolled_back_by')->nullable()->after('imported_by')->constrained('users')->nullOnDelete();
            $table->timestamp('rolled_back_at')->nullable()->after('control_data');
            $table->string('rollback_reason', 500)->nullable()->after('rolled_back_at');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropForeign(['bank_statement_import_id']);
            $table->dropIndex(['bank_statement_import_id', 'status']);
            $table->dropColumn('bank_statement_import_id');
        });

        Schema::table('bank_statement_imports', function (Blueprint $table) {
            $table->dropForeign(['rolled_back_by']);
            $table->dropColumn(['rolled_back_by', 'rolled_back_at', 'rollback_reason']);
        });
    }
};
