<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_documents', function (Blueprint $table) {
            $table->timestamp('issued_at')->nullable()->after('due_date');
            $table->timestamp('locked_at')->nullable()->after('issued_at');
            $table->decimal('paid_amount', 12, 2)->default(0)->after('amount');
        });

        Schema::create('sales_document_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('paid_at');
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_document_payments');
        Schema::table('sales_documents', fn (Blueprint $table) => $table->dropColumn(['issued_at', 'locked_at', 'paid_amount']));
    }
};
