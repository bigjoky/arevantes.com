<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('status')->nullable();
            $table->decimal('current_power_kw', 10, 3)->nullable();
            $table->decimal('today_energy_kwh', 12, 3)->nullable();
            $table->string('status_message')->nullable();
            $table->json('provider_data')->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['installation_id', 'provider', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_readings');
    }
};
