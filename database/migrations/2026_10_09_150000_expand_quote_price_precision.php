<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->decimal('unit_price', 13, 3)->change();
            $table->decimal('total_with_tax', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->dropColumn('total_with_tax');
            $table->decimal('unit_price', 12, 2)->change();
        });
    }
};
