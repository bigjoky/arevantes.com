<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedBigInteger('next_number');
        });

        Schema::create('commercial_number_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('number');
            $table->string('reason', 500);
            $table->timestamp('created_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unique(['document_type', 'year', 'number'], 'commercial_number_reservation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_number_reservations');
        Schema::dropIfExists('opportunity_number_sequences');
    }
};
