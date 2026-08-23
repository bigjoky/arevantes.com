<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('lead_response_escalation_minutes')
                ->default(120)
                ->after('lead_response_sla_minutes');
        });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->timestamp('first_response_sla_escalated_at')->nullable()->after('first_response_sla_alerted_at');
            $table->index(
                ['first_response_sla_escalated_at', 'first_responded_at', 'deleted_at', 'first_response_sla_alerted_at'],
                'opportunities_response_sla_escalation_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropIndex('opportunities_response_sla_escalation_idx');
            $table->dropColumn('first_response_sla_escalated_at');
        });

        Schema::table('crm_settings', function (Blueprint $table): void {
            $table->dropColumn('lead_response_escalation_minutes');
        });
    }
};
