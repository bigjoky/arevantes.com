<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_documents', function (Blueprint $table): void {
            $table->uuid('creation_key')->nullable()->unique();
            $table->char('creation_fingerprint', 64)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            if (! DB::transactionLevel()) {
                throw new RuntimeException('Evidence inspection requires an exclusive transactional lock.');
            }
            DB::statement('LOCK TABLE sales_documents IN ACCESS EXCLUSIVE MODE');
        }
        if (DB::table('sales_documents')->whereNotNull('creation_key')->exists()) {
            throw new RuntimeException('Creation evidence exists; preserve idempotency columns.');
        }
        Schema::table('sales_documents', function (Blueprint $table): void {
            $table->dropUnique(['creation_key']);
            $table->dropColumn(['creation_key', 'creation_fingerprint']);
        });
    }
};
