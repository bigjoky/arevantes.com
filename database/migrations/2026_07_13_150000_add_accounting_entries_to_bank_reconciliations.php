<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')->nullable()->unique()->after('tax_return_payment_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_reconciliations', fn (Blueprint $table) => $table->dropConstrainedForeignId('journal_entry_id'));
    }
};
