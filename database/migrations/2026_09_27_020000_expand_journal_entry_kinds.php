<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('entry_kind', 64)->default('automatic')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('journal_entries')->whereRaw('length(entry_kind) > 20')->exists()) {
            throw new RuntimeException('Cannot shorten journal entry kinds while longer kinds exist.');
        }

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('entry_kind', 20)->default('automatic')->change();
        });
    }
};
