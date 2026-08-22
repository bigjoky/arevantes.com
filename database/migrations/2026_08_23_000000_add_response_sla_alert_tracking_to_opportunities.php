<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->timestamp('first_response_sla_alerted_at')->nullable()->after('first_responded_at');
            $table->index(
                ['first_response_sla_alerted_at', 'first_responded_at', 'deleted_at', 'first_response_due_at'],
                'opportunities_response_sla_alert_lookup_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropIndex('opportunities_response_sla_alert_lookup_idx');
            $table->dropColumn('first_response_sla_alerted_at');
        });
    }
};
