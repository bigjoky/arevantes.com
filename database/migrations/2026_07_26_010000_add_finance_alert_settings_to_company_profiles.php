<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->decimal('operating_variance_alert_percent', 6, 2)->default(10);
            $table->decimal('cash_variance_alert_percent', 6, 2)->default(10);
            $table->decimal('liquidity_buffer_amount', 14, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'operating_variance_alert_percent',
                'cash_variance_alert_percent',
                'liquidity_buffer_amount',
            ]);
        });
    }
};
