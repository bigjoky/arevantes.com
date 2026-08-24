<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->unsignedSmallInteger('electrical_voltage_v')->nullable()->after('electrical_scope');
            $table->string('electrical_phase')->nullable()->after('electrical_voltage_v');
            $table->decimal('contracted_power_kw', 8, 2)->nullable()->after('electrical_phase');
            $table->boolean('electrical_certificate_required')->default(false)->after('contracted_power_kw');
            $table->string('security_system_type')->nullable()->after('electrical_certificate_required');
            $table->unsignedSmallInteger('security_zones')->nullable()->after('security_system_type');
            $table->string('security_monitoring_mode')->nullable()->after('security_zones');
            $table->string('security_grade')->nullable()->after('security_monitoring_mode');
        });
    }

    public function down(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->dropColumn([
                'electrical_voltage_v',
                'electrical_phase',
                'contracted_power_kw',
                'electrical_certificate_required',
                'security_system_type',
                'security_zones',
                'security_monitoring_mode',
                'security_grade',
            ]);
        });
    }
};
