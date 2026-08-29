<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_cycle_deviations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_cycle_commitment_id')->constrained()->cascadeOnDelete();
            $table->string('business_line', 40);
            $table->string('category', 30);
            $table->string('severity', 20)->default('medium');
            $table->string('status', 20)->default('open');
            $table->decimal('planned_value', 14, 3)->nullable();
            $table->decimal('actual_value', 14, 3)->nullable();
            $table->decimal('variance_percent', 10, 2)->nullable();
            $table->decimal('tolerance_percent', 10, 2)->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('summary');
            $table->json('detection_context')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('review_due_on');
            $table->timestamp('detected_at');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->unique(['service_cycle_commitment_id', 'category'], 'service_cycle_deviation_category_unique');
            $table->index(['status', 'review_due_on'], 'service_cycle_deviation_review_idx');
            $table->index(['business_line', 'category', 'status'], 'service_cycle_deviation_quality_idx');
        });

        Schema::create('service_cycle_deviation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_cycle_deviation_id')->constrained()->cascadeOnDelete();
            $table->string('event', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->json('changes')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['service_cycle_deviation_id', 'created_at'], 'service_cycle_deviation_event_history_idx');
        });

        Schema::create('service_cycle_deviation_assets', function (Blueprint $table): void {
            $table->foreignId('service_cycle_deviation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installation_asset_id')->constrained()->cascadeOnDelete();
            $table->primary(['service_cycle_deviation_id', 'installation_asset_id'], 'service_cycle_deviation_asset_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_cycle_deviation_assets');
        Schema::dropIfExists('service_cycle_deviation_events');
        Schema::dropIfExists('service_cycle_deviations');
    }
};
