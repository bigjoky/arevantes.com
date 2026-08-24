<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table): void {
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->text('completion_notes')->nullable()->after('completed_by');
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn('completion_notes');
        });
    }
};
