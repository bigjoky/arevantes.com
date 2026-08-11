<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by');
            $table->index(['type', 'status', 'assigned_to'], 'purchase_open_assignment_idx');
        });

        DB::table('purchase_documents')
            ->where('type', 'purchase_order')
            ->whereIn('status', ['ordered', 'partially_received'])
            ->whereNotNull('created_by')
            ->update([
                'assigned_to' => DB::raw('created_by'),
                'assigned_by' => DB::raw('created_by'),
                'assigned_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropIndex('purchase_open_assignment_idx');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn('assigned_at');
        });
    }
};
