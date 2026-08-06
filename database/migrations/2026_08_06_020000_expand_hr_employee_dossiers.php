<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employment_profiles', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('employee_number');
            $table->string('professional_category')->nullable()->after('job_title');
            $table->string('schedule_type')->nullable()->after('work_location');
            $table->text('annual_reference_salary')->nullable()->after('weekly_hours');
            $table->string('collective_agreement')->nullable()->after('annual_reference_salary');
            $table->string('cost_center')->nullable()->after('collective_agreement');
            $table->string('payroll_code')->nullable()->after('cost_center');
        });

        Schema::create('hr_contract_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('effective_date');
            $table->string('reason');
            $table->text('previous_values')->nullable();
            $table->text('new_values');
            $table->timestamps();

            $table->index(['person_id', 'effective_date']);
        });

        Schema::create('hr_emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->text('phone');
            $table->text('prevention_notes')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['person_id', 'is_primary']);
        });

        Schema::create('hr_employment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category');
            $table->string('title');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('visibility')->default('hr_only');
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'category']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employment_documents');
        Schema::dropIfExists('hr_emergency_contacts');
        Schema::dropIfExists('hr_contract_changes');

        Schema::table('hr_employment_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'job_title',
                'professional_category',
                'schedule_type',
                'annual_reference_salary',
                'collective_agreement',
                'cost_center',
                'payroll_code',
            ]);
        });
    }
};
