<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_cycle_commitments', function (Blueprint $table): void {
            $table->json('completion_snapshot')->nullable()->after('schedule_confirmed_at');
            $table->foreignId('completed_by')->nullable()->after('completion_snapshot')->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('completed_by');
            $table->index(['status', 'completed_at'], 'service_cycle_completion_status_idx');
        });

        Schema::table('asset_events', function (Blueprint $table): void {
            $table->foreignId('service_cycle_commitment_id')->nullable()->after('service_ticket_id')->constrained()->nullOnDelete();
            $table->unique(['installation_asset_id', 'service_cycle_commitment_id'], 'asset_event_service_cycle_unique');
            $table->index('service_cycle_commitment_id', 'asset_event_service_cycle_idx');
        });
        Schema::table('installation_assets', function (Blueprint $table): void {
            $table->timestamp('last_serviced_at')->nullable()->after('removed_at')->index();
            $table->unsignedInteger('service_count')->default(0)->after('last_serviced_at');
        });
    }

    public function down(): void
    {
        Schema::table('installation_assets', function (Blueprint $table): void {
            $table->dropIndex(['last_serviced_at']);
            $table->dropColumn(['last_serviced_at', 'service_count']);
        });
        Schema::table('asset_events', function (Blueprint $table): void {
            $table->dropIndex('asset_event_service_cycle_idx');
            $table->dropUnique('asset_event_service_cycle_unique');
            $table->dropConstrainedForeignId('service_cycle_commitment_id');
        });
        Schema::table('service_cycle_commitments', function (Blueprint $table): void {
            $table->dropIndex('service_cycle_completion_status_idx');
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn(['completion_snapshot', 'completed_at']);
        });
    }
};
