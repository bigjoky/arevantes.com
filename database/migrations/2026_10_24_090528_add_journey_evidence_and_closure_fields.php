<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_status_histories', function (Blueprint $table) {
            $table->string('signed_by_name')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_name')->nullable();
            $table->unsignedInteger('document_version')->nullable();
        });
        Schema::table('installations', function (Blueprint $table) {
            // Null preserves the absence of an explicit requirement on historical projects.
            $table->boolean('technical_validation_required')->nullable();
            $table->timestamp('dossier_closed_at')->nullable();
            $table->foreignId('dossier_closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('dossier_closure_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quote_status_histories', fn (Blueprint $table) => $table->dropColumn(['signed_by_name', 'proof_path', 'proof_name', 'document_version']));
        Schema::table('installations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dossier_closed_by');
            $table->dropColumn(['technical_validation_required', 'dossier_closed_at', 'dossier_closure_reason']);
        });
    }
};
