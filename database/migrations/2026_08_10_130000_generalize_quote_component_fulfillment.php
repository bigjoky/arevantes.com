<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('quote_line_components')->whereIn('fulfillment_kind', ['task', 'legalization'])->update(['fulfillment_kind' => 'activity']);
        DB::table('quote_line_components')->where('fulfillment_kind', 'maintenance')->update(['fulfillment_kind' => 'recurring']);
        DB::table('quote_line_components')->where('fulfillment_kind', 'material')->update(['fulfillment_kind' => 'stock']);
    }

    public function down(): void
    {
        DB::table('quote_line_components')->where('fulfillment_kind', 'activity')->update(['fulfillment_kind' => 'task']);
        DB::table('quote_line_components')->where('fulfillment_kind', 'recurring')->update(['fulfillment_kind' => 'maintenance']);
        DB::table('quote_line_components')->where('fulfillment_kind', 'stock')->update(['fulfillment_kind' => 'material']);
    }
};
