<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_visits', function (Blueprint $table): void {
            // Cold canvassing must not create placeholder people or opportunities.
            $table->foreignId('person_id')->nullable()->change();
            $table->foreignId('opportunity_id')->nullable()->change();
            $table->string('prospect_type', 20)->nullable();
            $table->string('outcome', 30)->nullable();
            $table->foreignId('commercial_route_plan_id')->nullable()
                ->constrained('commercial_route_plans')->nullOnDelete();
            $table->index(['user_id', 'visited_at', 'outcome'], 'commercial_visits_activity_idx');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_visits', function (Blueprint $table): void {
            $table->dropIndex('commercial_visits_activity_idx');
            $table->dropConstrainedForeignId('commercial_route_plan_id');
            $table->dropColumn(['prospect_type', 'outcome']);
        });

        // Existing prospecting-only visits may have no person/opportunity.
        // Intentionally do not restore NOT NULL constraints automatically.
    }
};
