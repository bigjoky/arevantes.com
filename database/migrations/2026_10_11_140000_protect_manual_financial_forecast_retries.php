<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_forecast_items', fn (Blueprint $table) => $table->uuid('operation_key')->nullable()->unique());
    }

    public function down(): void
    {
        Schema::table('financial_forecast_items', function (Blueprint $table) {
            $table->dropUnique(['operation_key']);
            $table->dropColumn('operation_key');
        });
    }
};
