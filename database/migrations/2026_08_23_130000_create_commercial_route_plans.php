<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commercial_route_plans')) {
            Schema::create('commercial_route_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('route_date');
                $table->string('status', 20)->default('planned');
                $table->decimal('origin_latitude', 10, 7)->nullable();
                $table->decimal('origin_longitude', 11, 7)->nullable();
                $table->timestamp('optimized_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'route_date']);
                $table->index(['route_date', 'status']);
            });
        }

        if (! Schema::hasTable('commercial_route_stops')) {
            Schema::create('commercial_route_stops', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('commercial_route_plan_id')->constrained()->cascadeOnDelete();
                $table->foreignId('crm_activity_id')->nullable()->constrained('crm_activities')->nullOnDelete();
                $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
                $table->unsignedSmallInteger('position');
                $table->string('status', 20)->default('planned');
                $table->string('title');
                $table->string('address', 500)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 11, 7)->nullable();
                $table->timestamp('planned_at')->nullable();
                $table->timestamp('arrived_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->decimal('arrival_latitude', 10, 7)->nullable();
                $table->decimal('arrival_longitude', 11, 7)->nullable();
                $table->decimal('arrival_accuracy_meters', 10, 2)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(
                    ['commercial_route_plan_id', 'crm_activity_id'],
                    'route_stop_activity_unique'
                );
                $table->index(['commercial_route_plan_id', 'position'], 'route_stop_position_idx');
                $table->index(['commercial_route_plan_id', 'status'], 'route_stop_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_route_stops');
        Schema::dropIfExists('commercial_route_plans');
    }
};
