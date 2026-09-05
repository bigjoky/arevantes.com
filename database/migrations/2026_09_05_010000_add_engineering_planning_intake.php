<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engineering_dossiers', function (Blueprint $table) {
            $table->string('planning_status')->nullable()->index();
            $table->string('planning_fingerprint', 64)->nullable();
            $table->text('planning_note')->nullable();
            $table->foreignId('planning_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('planning_requested_at')->nullable();
            $table->text('planning_review_note')->nullable();
            $table->foreignId('planning_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('planning_reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('engineering_dossiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('planning_requested_by');
            $table->dropConstrainedForeignId('planning_reviewed_by');
            $table->dropColumn(['planning_status', 'planning_fingerprint', 'planning_note', 'planning_requested_at', 'planning_review_note', 'planning_reviewed_at']);
        });
    }
};
