<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_leave_policies', function (Blueprint $table) {
            $table->string('day_count_method')->default('calendar_days')->after('accrual_method');
        });

        DB::table('hr_leave_policies')
            ->whereIn('type', ['vacation', 'personal'])
            ->update(['day_count_method' => 'working_days']);

        Schema::create('hr_calendar_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('hr_leave_requests', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('reviewed_by');
            $table->index('substitute_person_id');
        });
        Schema::table('hr_employee_tasks', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('created_by');
            $table->index('completed_by');
        });
        Schema::table('hr_emergency_contacts', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('hr_employment_documents', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('uploaded_by');
        });
        Schema::table('hr_contract_changes', fn (Blueprint $table) => $table->index('changed_by'));
        Schema::table('hr_employment_profiles', function (Blueprint $table) {
            $table->index('department_id');
            $table->index('manager_person_id');
        });
        Schema::table('hr_leave_attachments', fn (Blueprint $table) => $table->index('uploaded_by'));

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table hr_leave_policies add constraint hr_leave_policies_day_count_method_check check (day_count_method in ('calendar_days', 'working_days'))");
            DB::statement("alter table hr_leave_policies add constraint hr_leave_policies_accrual_method_check check (accrual_method in ('annual', 'monthly', 'none'))");
            DB::statement("alter table hr_leave_requests add constraint hr_leave_requests_status_check check (status in ('pending', 'approved', 'rejected', 'cancelled'))");
            DB::statement("alter table hr_employee_tasks add constraint hr_employee_tasks_status_check check (status in ('pending', 'in_progress', 'completed'))");
            DB::statement("create index hr_leave_requests_pending_dates_idx on hr_leave_requests (start_date, end_date) where status = 'pending' and deleted_at is null");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('drop index if exists hr_leave_requests_pending_dates_idx');
            DB::statement('alter table hr_employee_tasks drop constraint if exists hr_employee_tasks_status_check');
            DB::statement('alter table hr_leave_requests drop constraint if exists hr_leave_requests_status_check');
            DB::statement('alter table hr_leave_policies drop constraint if exists hr_leave_policies_accrual_method_check');
            DB::statement('alter table hr_leave_policies drop constraint if exists hr_leave_policies_day_count_method_check');
        }

        Schema::table('hr_leave_attachments', fn (Blueprint $table) => $table->dropIndex(['uploaded_by']));
        Schema::table('hr_employment_profiles', function (Blueprint $table) {
            $table->dropIndex(['department_id']);
            $table->dropIndex(['manager_person_id']);
        });
        Schema::table('hr_contract_changes', fn (Blueprint $table) => $table->dropIndex(['changed_by']));
        Schema::table('hr_employment_documents', function (Blueprint $table) {
            $table->dropIndex(['uploaded_by']);
            $table->dropSoftDeletes();
        });
        Schema::table('hr_emergency_contacts', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('hr_employee_tasks', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropIndex(['completed_by']);
            $table->dropSoftDeletes();
        });
        Schema::table('hr_leave_requests', function (Blueprint $table) {
            $table->dropIndex(['reviewed_by']);
            $table->dropIndex(['substitute_person_id']);
            $table->dropSoftDeletes();
        });
        Schema::dropIfExists('hr_calendar_holidays');
        Schema::table('hr_leave_policies', fn (Blueprint $table) => $table->dropColumn('day_count_method'));
    }
};
