<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->foreignId('person_id')->nullable()->change();
            $table->foreign('person_id')->references('id')->on('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('installations')->whereNull('person_id')->delete();

        Schema::table('installations', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->foreignId('person_id')->nullable(false)->change();
            $table->foreign('person_id')->references('id')->on('people')->cascadeOnDelete();
        });
    }
};
