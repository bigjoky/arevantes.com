<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_fiscal_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('operation_class', 32);
            $table->string('evidence_fingerprint', 64);
            $table->json('evidence_snapshot');
            $table->foreignId('reviewed_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at');
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_fiscal_classifications');
    }
};
