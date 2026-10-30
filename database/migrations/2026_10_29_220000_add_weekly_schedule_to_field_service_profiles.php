<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_service_profiles', function (Blueprint $table) {
            // Null means unknown, [] means an explicitly non-working week. ISO weekdays 1–7.
            $table->json('weekly_schedule')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve configured working schedules; use a reviewed forward migration.');
    }
};
