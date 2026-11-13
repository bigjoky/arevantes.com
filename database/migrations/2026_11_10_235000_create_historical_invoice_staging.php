<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historical_invoice_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users');
            $table->string('source', 16);
            $table->timestampsTz();
        });

        Schema::create('historical_invoice_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('historical_invoice_batches');
            $table->unsignedInteger('row_number');
            $table->string('status', 32)->default('review_required');
            $table->json('document');
            $table->json('errors');
            $table->foreignId('created_by')->constrained('users');
            $table->timestampsTz();
            $table->unique(['batch_id', 'row_number']);
            $table->index(['batch_id', 'status']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE historical_invoice_batches ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE historical_invoice_rows ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_invoice_rows');
        Schema::dropIfExists('historical_invoice_batches');
    }
};
