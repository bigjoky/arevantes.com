<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', fn (Blueprint $table) => $table->unsignedInteger('dossier_revision')->default(0));
    }

    public function down(): void
    {
        Schema::table('installations', fn (Blueprint $table) => $table->dropColumn('dossier_revision'));
    }
};
