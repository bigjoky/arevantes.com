<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->string('huawei_station_code')->nullable()->unique()->after('deye_synced_at');
            $table->string('huawei_station_name')->nullable();
            $table->string('huawei_status')->nullable()->index();
            $table->decimal('huawei_current_power_kw', 10, 3)->nullable();
            $table->decimal('huawei_today_energy_kwh', 12, 3)->nullable();
            $table->string('huawei_status_message')->nullable();
            $table->json('huawei_data')->nullable();
            $table->timestamp('huawei_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('installations', fn (Blueprint $table) => $table->dropColumn(['huawei_station_code', 'huawei_station_name', 'huawei_status', 'huawei_current_power_kw', 'huawei_today_energy_kwh', 'huawei_status_message', 'huawei_data', 'huawei_synced_at']));
    }
};
