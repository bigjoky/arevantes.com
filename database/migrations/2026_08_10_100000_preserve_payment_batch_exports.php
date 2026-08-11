<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_batches', function (Blueprint $table) {
            $table->foreignId('exported_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->string('export_path')->nullable()->after('exported_at');
            $table->string('export_sha256', 64)->nullable()->after('export_path');
        });
    }

    public function down(): void
    {
        Schema::table('payment_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exported_by');
            $table->dropColumn(['export_path', 'export_sha256']);
        });
    }
};
