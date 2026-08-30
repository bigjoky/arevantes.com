<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_scenario_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planning_scenario_id')->constrained()->restrictOnDelete();
            $table->enum('area', ['operations', 'inventory', 'purchases', 'finance']);
            $table->enum('status', ['draft', 'confirmed'])->default('draft');
            $table->char('approved_fingerprint', 64);
            $table->char('request_fingerprint', 64);
            $table->json('payload');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->text('confirmation_note')->nullable();
            $table->timestampsTz();

            $table->unique(['planning_scenario_id', 'area'], 'planning_scenario_requests_area_unique');
            $table->index(['area', 'status'], 'planning_scenario_requests_queue_index');
            $table->index('generated_by', 'planning_scenario_requests_generated_by_index');
            $table->index('confirmed_by', 'planning_scenario_requests_confirmed_by_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_scenario_requests');
    }
};
