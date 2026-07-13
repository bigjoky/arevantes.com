<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->string('matching_status', 30)->nullable()->after('status')->index();
            $table->decimal('match_variance_amount', 12, 2)->default(0)->after('amount');
            $table->timestamp('approved_at')->nullable()->after('locked_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->text('approval_notes')->nullable()->after('approved_by');
        });

        Schema::table('purchase_document_lines', function (Blueprint $table) {
            $table->foreignId('source_line_id')->nullable()->after('purchase_document_id')->constrained('purchase_document_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_document_lines', fn (Blueprint $table) => $table->dropConstrainedForeignId('source_line_id'));
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropIndex(['matching_status']);
            $table->dropColumn(['matching_status', 'match_variance_amount', 'approved_at', 'approval_notes']);
        });
    }
};
