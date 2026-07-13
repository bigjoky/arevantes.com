<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename');
            $table->string('file_hash', 64)->index();
            $table->string('format', 20);
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('opening_balance', 14, 2)->nullable();
            $table->decimal('closing_balance', 14, 2)->nullable();
            $table->unsignedInteger('created_transactions')->default(0);
            $table->unsignedInteger('duplicate_transactions')->default(0);
            $table->boolean('control_valid')->nullable();
            $table->json('control_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_imports');
    }
};
