<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_document_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('file_hash', 64)->unique();
            $table->string('extraction_status', 30)->default('pending')->index();
            $table->json('extracted_data')->nullable();
            $table->text('extraction_error')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_document_attachments');
    }
};
