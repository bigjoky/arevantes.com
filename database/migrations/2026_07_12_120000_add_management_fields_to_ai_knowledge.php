<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('original_filename')->nullable()->after('source');
            $table->unsignedBigInteger('file_size')->nullable()->after('original_filename');
        });

        Schema::table('ai_generations', function (Blueprint $table) {
            $table->string('mode')->default('operational')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ai_generations', fn (Blueprint $table) => $table->dropColumn('mode'));
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['original_filename', 'file_size']);
        });
    }
};
