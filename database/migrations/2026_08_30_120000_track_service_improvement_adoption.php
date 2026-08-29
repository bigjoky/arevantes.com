<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_corrective_proposals', function (Blueprint $table): void {
            $table->foreignId('superseded_by_proposal_id')->nullable()->after('applied_at')->constrained('service_corrective_proposals')->nullOnDelete();
            $table->text('supersession_note')->nullable()->after('superseded_by_proposal_id');
            $table->foreignId('superseded_by')->nullable()->after('supersession_note')->constrained('users')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable()->after('superseded_by');
            $table->index(['status', 'superseded_at'], 'service_corrective_proposal_monitoring_idx');
        });
    }

    public function down(): void
    {
        Schema::table('service_corrective_proposals', function (Blueprint $table): void {
            $table->dropIndex('service_corrective_proposal_monitoring_idx');
            $table->dropConstrainedForeignId('superseded_by_proposal_id');
            $table->dropConstrainedForeignId('superseded_by');
            $table->dropColumn(['supersession_note', 'superseded_at']);
        });
    }
};
