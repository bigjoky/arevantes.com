<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_portal_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->unique()->constrained('people')->cascadeOnDelete();
            $table->boolean('email_documents')->default(true);
            $table->boolean('email_tickets')->default(true);
            $table->boolean('email_maintenance')->default(true);
            $table->timestamps();
        });

        Schema::create('customer_portal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('customer_portal_access_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 50);
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['person_id', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('customer_document_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('customer_portal_access_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type', 30);
            $table->unsignedBigInteger('document_id');
            $table->string('document_version', 80);
            $table->string('document_hash', 64);
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->unique(['person_id', 'document_type', 'document_id', 'document_version'], 'portal_acceptance_version_unique');
        });

        Schema::create('customer_satisfaction_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('customer_portal_access_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('installation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('context', 30);
            $table->unsignedTinyInteger('score');
            $table->text('comments')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->index(['person_id', 'submitted_at']);
        });

        Schema::table('document_deliveries', function (Blueprint $table) {
            $table->string('document_version', 80)->nullable()->after('status');
            $table->string('document_hash', 64)->nullable()->after('document_version');
            $table->timestamp('last_downloaded_at')->nullable()->after('first_viewed_at');
            $table->timestamp('revoked_at')->nullable()->after('responded_at');
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['document_version', 'document_hash', 'last_downloaded_at', 'revoked_at']);
        });
        Schema::dropIfExists('customer_satisfaction_surveys');
        Schema::dropIfExists('customer_document_acceptances');
        Schema::dropIfExists('customer_portal_events');
        Schema::dropIfExists('customer_portal_preferences');
    }
};
