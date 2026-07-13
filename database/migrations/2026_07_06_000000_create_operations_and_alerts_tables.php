<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->foreignId('commercial_user_id')->nullable()->after('person_id')->constrained('users')->nullOnDelete();
            $table->foreignId('technician_user_id')->nullable()->after('commercial_user_id')->constrained('users')->nullOnDelete();
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20)->default('task');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('pending');
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number')->unique();
            $table->string('status', 20)->default('draft');
            $table->decimal('amount', 12, 2);
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('monitoring_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('status', 20);
            $table->string('message');
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['installation_id', 'provider', 'resolved_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('monitoring_alerts');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('crm_activities');
        Schema::table('installations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('technician_user_id');
            $table->dropConstrainedForeignId('commercial_user_id');
        });
    }
};
