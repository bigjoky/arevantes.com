<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historical_invoice_rows', function (Blueprint $table) {
            $table->json('evidence')->nullable();
        });
        DB::table('permissions')->insertOrIgnore(['key' => 'manage-historical-invoices-2026', 'name' => 'Regularización histórica de facturas 2026', 'category' => 'finance', 'is_sensitive' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('historical_invoice_rows', fn (Blueprint $table) => $table->dropColumn('evidence'));
    }
};
