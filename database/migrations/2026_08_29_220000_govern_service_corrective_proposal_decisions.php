<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_corrective_proposals', function (Blueprint $table): void {
            $table->string('target_type', 40)->nullable()->after('payload');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            $table->string('target_version', 100)->nullable()->after('target_id');
            $table->json('target_snapshot')->nullable()->after('target_version');
            $table->text('decision_note')->nullable()->after('status');
            $table->foreignId('decided_by')->nullable()->after('decision_note')->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable()->after('decided_by');
            $table->unsignedSmallInteger('resubmission_count')->default(0)->after('decided_at');
            $table->index(['status', 'created_at'], 'service_corrective_proposal_inbox_idx');
        });
    }

    public function down(): void
    {
        Schema::table('service_corrective_proposals', function (Blueprint $table): void {
            $table->dropIndex('service_corrective_proposal_inbox_idx');
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn([
                'target_type', 'target_id', 'target_version', 'target_snapshot',
                'decision_note', 'decided_at', 'resubmission_count',
            ]);
        });
    }
};
