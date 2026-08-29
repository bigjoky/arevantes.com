<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_corrective_proposals', function (Blueprint $table): void {
            $table->text('application_note')->nullable()->after('resubmission_count');
            $table->json('application_result')->nullable()->after('application_note');
            $table->foreignId('applied_by')->nullable()->after('application_result')->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable()->after('applied_by');
        });

        Schema::create('service_corrective_proposal_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_corrective_proposal_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->json('input')->nullable();
            $table->json('target_before')->nullable();
            $table->json('target_after')->nullable();
            $table->json('result')->nullable();
            $table->string('error_code', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['service_corrective_proposal_id', 'status'], 'service_corrective_proposal_application_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_corrective_proposal_applications');
        Schema::table('service_corrective_proposals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('applied_by');
            $table->dropColumn(['application_note', 'application_result', 'applied_at']);
        });
    }
};
