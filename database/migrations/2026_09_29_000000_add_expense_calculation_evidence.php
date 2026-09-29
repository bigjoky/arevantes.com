<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('tax_calculation_version', 32)->nullable();
            $table->json('tax_calculation_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('expenses')->whereNotNull('tax_calculation_version')->orWhereNotNull('tax_calculation_snapshot')->exists()) {
            throw new RuntimeException('Cannot remove persisted expense calculation evidence.');
        }
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['tax_calculation_version', 'tax_calculation_snapshot']);
        });
    }
};
