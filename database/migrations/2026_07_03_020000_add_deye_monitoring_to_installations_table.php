<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->string('deye_station_id')->nullable()->unique()->after('service_type');
            $table->string('deye_station_name')->nullable()->after('deye_station_id');
            $table->string('deye_status')->nullable()->index()->after('deye_station_name');
            $table->decimal('deye_current_power_kw', 10, 3)->nullable()->after('deye_status');
            $table->decimal('deye_today_energy_kwh', 12, 3)->nullable()->after('deye_current_power_kw');
            $table->string('deye_status_message')->nullable()->after('deye_today_energy_kwh');
            $table->json('deye_data')->nullable()->after('deye_status_message');
            $table->timestamp('deye_synced_at')->nullable()->after('deye_data');
        });
    }

    public function down(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->dropColumn(['deye_station_id', 'deye_station_name', 'deye_status', 'deye_current_power_kw', 'deye_today_energy_kwh', 'deye_status_message', 'deye_data', 'deye_synced_at']);
        });
    }
};
