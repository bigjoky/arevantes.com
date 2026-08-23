<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('supply_criticality', 20)->default('standard')->after('track_serials');
            $table->unsignedSmallInteger('preferred_lead_time_days')->default(7)->after('supply_criticality');
            $table->index(['supply_criticality', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['supply_criticality', 'is_active']);
            $table->dropColumn(['supply_criticality', 'preferred_lead_time_days']);
        });
    }
};
