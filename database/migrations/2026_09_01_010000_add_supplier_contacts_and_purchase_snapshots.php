<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('position')->nullable();
            $table->json('roles')->nullable();
            $table->string('document_type', 30)->nullable();
            $table->string('document_number')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('preferred_channel', 20)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_authorized_signatory')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['supplier_id', 'is_primary']);
        });

        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->json('supplier_snapshot')->nullable()->after('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_documents', fn (Blueprint $table) => $table->dropColumn('supplier_snapshot'));
        Schema::dropIfExists('supplier_contacts');
    }
};
