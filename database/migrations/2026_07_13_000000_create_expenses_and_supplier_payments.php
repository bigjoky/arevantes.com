<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number')->unique();
            $table->string('type', 20);
            $table->string('document_number')->nullable();
            $table->string('merchant_name')->nullable();
            $table->date('expense_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_date']);
        });

        Schema::create('expense_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('paid_at');
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->decimal('paid_amount', 12, 2)->default(0)->after('amount');
        });

        $now = now();
        DB::table('expense_categories')->insert([
            ['code' => 'materials', 'name' => 'Materials and supplies', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'fuel', 'name' => 'Fuel and vehicle', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'travel', 'name' => 'Travel and meals', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'tools', 'name' => 'Tools and equipment', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'services', 'name' => 'Professional services', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'utilities', 'name' => 'Utilities and communications', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'other', 'name' => 'Other expenses', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('purchase_documents', fn (Blueprint $table) => $table->dropColumn('paid_amount'));
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('expense_attachments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
