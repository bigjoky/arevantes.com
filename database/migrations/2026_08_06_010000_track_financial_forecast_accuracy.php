<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_forecast_items', function (Blueprint $table) {
            $table->decimal('baseline_amount', 14, 2)->nullable()->after('amount');
            $table->date('baseline_due_on')->nullable()->after('due_on');
            $table->decimal('actual_amount', 14, 2)->nullable()->after('status');
            $table->date('settled_on')->nullable()->after('actual_amount');
            $table->text('settlement_notes')->nullable()->after('settled_on');
            $table->foreignId('settled_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->index(['status', 'settled_on']);
        });

        DB::table('financial_forecast_items')->update([
            'baseline_amount' => DB::raw('amount'),
            'baseline_due_on' => DB::raw('due_on'),
        ]);
    }

    public function down(): void
    {
        Schema::table('financial_forecast_items', function (Blueprint $table) {
            $table->dropIndex(['status', 'settled_on']);
            $table->dropConstrainedForeignId('settled_by');
            $table->dropColumn([
                'baseline_amount',
                'baseline_due_on',
                'actual_amount',
                'settled_on',
                'settlement_notes',
            ]);
        });
    }
};
