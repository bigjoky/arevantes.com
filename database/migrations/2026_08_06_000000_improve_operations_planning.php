<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->text('blocked_reason')->nullable()->after('status');
            $table->index(['assigned_user_id', 'status', 'due_at'], 'activities_owner_status_due_index');
            $table->index(['status', 'starts_at'], 'activities_status_start_index');
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropIndex('activities_owner_status_due_index');
            $table->dropIndex('activities_status_start_index');
            $table->dropColumn('blocked_reason');
        });
    }
};
