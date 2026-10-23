<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('category', 32);
            $table->string('visibility', 16)->default('restricted');
            $table->string('status', 16)->default('active');
            $table->date('expires_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('revision')->default(1);
            $table->uuid('upload_token');
            $table->char('request_fingerprint', 64);
            $table->unique(['company_profile_id', 'upload_token']);
            $table->timestamps();
            $table->index(['company_profile_id', 'status', 'category']);
            $table->index(['company_profile_id', 'expires_at']);
        });
        Schema::create('company_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_document_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->timestamps();
            $table->unique(['company_document_id', 'version']);
        });
    }

    public function down(): void
    {
        if (DB::table('company_documents')->exists()) {
            throw new RuntimeException('Corporate documents exist; rollback would destroy their history.');
        }
        Schema::dropIfExists('company_document_versions');
        Schema::dropIfExists('company_documents');
    }
};
