<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_inbox_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_documents')->nullOnDelete();
            $table->foreignId('purchase_document_id')->nullable()->unique()->constrained('purchase_documents')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('classification', 30)->default('unknown');
            $table->string('status', 30)->default('pending_review')->index();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('file_hash', 64)->unique();
            $table->string('extraction_status', 30)->default('pending')->index();
            $table->json('extracted_data')->nullable();
            $table->text('extraction_error')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['classification', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_inbox_documents');
    }
};
