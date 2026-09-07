<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engineering_schedule_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engineering_dossier_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('dossier_version');
            $table->date('proposed_on');
            $table->foreignId('assignee_id')->constrained('users')->restrictOnDelete();
            $table->decimal('estimated_hours', 6, 2);
            $table->string('status')->default('pending');
            $table->string('fingerprint', 64);
            $table->text('note');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engineering_schedule_proposals');
    }
};
