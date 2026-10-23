<?php

use App\Support\Database\BackendTableSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_fiscal_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_document_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('operation_class', 32);
            $table->string('evidence_fingerprint', 64);
            $table->json('evidence_snapshot');
            $table->foreignId('reviewed_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at');
            $table->text('reason');
            $table->string('source_reference', 255);
            $table->timestamps();
        });
        BackendTableSecurity::restrict('purchase_fiscal_classifications');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_fiscal_classifications');
    }
};
