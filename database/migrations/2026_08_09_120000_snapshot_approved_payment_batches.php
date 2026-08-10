<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_batch_items', function (Blueprint $table) {
            $table->string('recipient_name')->nullable()->after('amount');
            $table->string('recipient_iban', 34)->nullable()->after('recipient_name');
            $table->string('recipient_bic', 11)->nullable()->after('recipient_iban');
            $table->string('payment_reference')->nullable()->after('recipient_bic');
        });
    }

    public function down(): void
    {
        Schema::table('payment_batch_items', function (Blueprint $table) {
            $table->dropColumn(['recipient_name', 'recipient_iban', 'recipient_bic', 'payment_reference']);
        });
    }
};
