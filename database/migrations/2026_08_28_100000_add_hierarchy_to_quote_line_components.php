<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_line_components', function (Blueprint $table) {
            $table->foreignId('parent_component_id')->nullable()->after('quote_line_id')->constrained('quote_line_components')->cascadeOnDelete();
            $table->unsignedTinyInteger('depth')->default(2)->after('parent_component_id');
            $table->index(['quote_line_id', 'parent_component_id', 'position'], 'quote_line_components_hierarchy_index');
        });
    }

    public function down(): void
    {
        Schema::table('quote_line_components', function (Blueprint $table) {
            $table->dropIndex('quote_line_components_hierarchy_index');
            $table->dropConstrainedForeignId('parent_component_id');
            $table->dropColumn('depth');
        });
    }
};
