<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 30)->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hr_employment_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->unique()->constrained('people')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('manager_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('employee_number', 50)->nullable()->unique();
            $table->string('employment_type')->default('permanent');
            $table->string('status')->default('active');
            $table->string('work_location')->nullable();
            $table->decimal('weekly_hours', 5, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('hr_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('requested_days');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date']);
            $table->index(['person_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_requests');
        Schema::dropIfExists('hr_employment_profiles');
        Schema::dropIfExists('hr_departments');
    }
};
