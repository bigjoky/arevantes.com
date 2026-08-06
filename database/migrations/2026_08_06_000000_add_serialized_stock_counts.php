<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_counts', function (Blueprint $table) {
            $table->string('mode', 20)->default('quantity')->after('number');
        });

        Schema::table('stock_count_lines', function (Blueprint $table) {
            $table->unsignedInteger('discrepancy_count')->default(0)->after('unit_cost');
            $table->json('snapshot_serials')->nullable()->after('discrepancy_count');
            $table->json('counted_serials')->nullable()->after('snapshot_serials');
            $table->json('missing_serials')->nullable()->after('counted_serials');
            $table->json('unexpected_serials')->nullable()->after('missing_serials');
        });

        DB::table('stock_count_lines')->where('difference_quantity', '!=', 0)->update(['discrepancy_count' => 1]);
    }

    public function down(): void
    {
        Schema::table('stock_count_lines', function (Blueprint $table) {
            $table->dropColumn(['discrepancy_count', 'snapshot_serials', 'counted_serials', 'missing_serials', 'unexpected_serials']);
        });

        Schema::table('stock_counts', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
