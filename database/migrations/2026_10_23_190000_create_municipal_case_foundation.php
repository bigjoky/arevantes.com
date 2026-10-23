<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('ine_code', 5)->unique();
            $table->string('name');
            $table->string('province');
            $table->string('configuration_status')->default('research'); // research, review, verified, stale
            $table->timestampTz('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('municipal_cases', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('installation_id')->constrained('installations')->restrictOnDelete();
            $table->foreignId('municipality_id')->constrained('municipalities')->restrictOnDelete();
            $table->foreignId('client_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('procedure_code')->nullable();
            $table->string('procedure_version')->nullable();
            $table->string('administrative_status')->default('draft');
            $table->string('execution_status')->default('not_assessed');
            $table->string('representation_status')->default('not_requested');
            $table->date('representation_expires_on')->nullable();
            $table->jsonb('input_snapshot')->nullable();
            $table->jsonb('urban_planning_checks')->nullable();
            $table->decimal('pem_amount', 14, 2)->nullable();
            $table->string('pem_currency', 3)->default('EUR');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('execution_authorized_at')->nullable();
            $table->foreignId('execution_authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['municipality_id', 'administrative_status']);
        });

        Schema::create('municipal_case_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipal_case_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('status')->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->string('private_disk')->nullable();
            $table->string('storage_path')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->jsonb('template_snapshot')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['municipal_case_id', 'kind', 'version']);
        });

        Schema::create('municipal_case_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipal_case_id')->constrained()->cascadeOnDelete();
            $table->string('registry_number')->nullable();
            $table->timestampTz('submitted_at');
            $table->string('proof_private_disk')->nullable();
            $table->string('proof_storage_path')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('municipal_case_tax_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipal_case_id')->constrained()->cascadeOnDelete();
            $table->string('tax_type'); // icio, urban_planning_fee, other
            $table->string('liable_party')->default('client');
            $table->string('payment_mode')->default('unverified'); // prior, deferred, unverified
            $table->string('payment_status')->default('unverified');
            $table->decimal('assessed_amount', 14, 2)->nullable();
            $table->date('notification_date')->nullable();
            $table->date('payment_deadline')->nullable();
            $table->string('proof_private_disk')->nullable();
            $table->string('proof_storage_path')->nullable();
            $table->timestampTz('proof_verified_at')->nullable();
            $table->foreignId('proof_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('benefit_status')->default('not_assessed');
            $table->date('benefit_application_deadline')->nullable();
            $table->string('benefit_registry_number')->nullable();
            $table->jsonb('source_snapshot')->nullable();
            $table->timestampsTz();
            $table->index(['payment_status', 'payment_deadline']);
        });

        Schema::create('municipal_case_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipal_case_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->jsonb('details')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('occurred_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_case_events');
        Schema::dropIfExists('municipal_case_tax_obligations');
        Schema::dropIfExists('municipal_case_submissions');
        Schema::dropIfExists('municipal_case_documents');
        Schema::dropIfExists('municipal_cases');
        Schema::dropIfExists('municipalities');
    }
};
