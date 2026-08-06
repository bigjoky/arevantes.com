<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_policies', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->string('name');
            $table->decimal('annual_allowance_days', 6, 2)->default(0);
            $table->string('accrual_method')->default('annual');
            $table->decimal('carryover_limit_days', 6, 2)->default(0);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hr_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('policy_id')->constrained('hr_leave_policies')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('carryover_days', 6, 2)->default(0);
            $table->decimal('adjustment_days', 6, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'policy_id', 'year']);
        });

        Schema::table('hr_leave_requests', function (Blueprint $table) {
            $table->foreignId('substitute_person_id')->nullable()->after('person_id')->constrained('people')->nullOnDelete();
        });

        Schema::create('hr_leave_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('hr_leave_requests')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        $now = now();
        DB::table('hr_leave_policies')->insert([
            ['type' => 'vacation', 'name' => 'Vacaciones', 'annual_allowance_days' => 22, 'accrual_method' => 'monthly', 'carryover_limit_days' => 5, 'requires_attachment' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'sick', 'name' => 'Baja médica', 'annual_allowance_days' => 0, 'accrual_method' => 'none', 'carryover_limit_days' => 0, 'requires_attachment' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'personal', 'name' => 'Permiso personal', 'annual_allowance_days' => 3, 'accrual_method' => 'annual', 'carryover_limit_days' => 0, 'requires_attachment' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'other', 'name' => 'Otra ausencia', 'annual_allowance_days' => 0, 'accrual_method' => 'none', 'carryover_limit_days' => 0, 'requires_attachment' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_attachments');
        Schema::table('hr_leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('substitute_person_id');
        });
        Schema::dropIfExists('hr_leave_balances');
        Schema::dropIfExists('hr_leave_policies');
    }
};
