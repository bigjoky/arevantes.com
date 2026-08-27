<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->foreignId('parent_line_id')->nullable()->after('quote_id')->constrained('quote_lines')->cascadeOnDelete();
            $table->unsignedTinyInteger('depth')->default(1)->after('parent_line_id');
            $table->index(['quote_id', 'parent_line_id', 'position'], 'quote_lines_hierarchy_index');
        });
    }

    public function down(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->dropIndex('quote_lines_hierarchy_index');
            $table->dropConstrainedForeignId('parent_line_id');
            $table->dropColumn('depth');
        });
    }
};
