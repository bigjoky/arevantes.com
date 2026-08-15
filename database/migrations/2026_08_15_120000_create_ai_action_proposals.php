<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_proposals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ai_generation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('status')->default('proposed');
            $table->json('payload');
            $table->string('idempotency_key', 64)->unique();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->foreignId('crm_activity_id')->nullable()->constrained('crm_activities')->nullOnDelete();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index('ai_generation_id');
            $table->index('installation_id');
            $table->index('proposed_by');
            $table->index('confirmed_by');
            $table->index('crm_activity_id');
            $table->index(['status', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ai_action_proposals ADD CONSTRAINT ai_action_proposals_status_check CHECK (status IN ('proposed', 'executed', 'cancelled', 'failed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_proposals');
    }
};
