<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->foreignId('work_order_id')->nullable()->after('installation_id')->constrained()->nullOnDelete();
            $table->decimal('consumed_quantity', 12, 3)->default(0)->after('quantity');
            $table->index(['work_order_id', 'status']);
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('work_order_id')->nullable()->after('installation_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_id');
        });
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropIndex(['work_order_id', 'status']);
            $table->dropConstrainedForeignId('work_order_id');
            $table->dropColumn('consumed_quantity');
        });
    }
};
