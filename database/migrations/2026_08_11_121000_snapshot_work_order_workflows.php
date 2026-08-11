<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->json('allowed_transitions_snapshot')->nullable()->after('service_zone');
        });

        $defaults = json_encode([
            'planned' => ['in_progress', 'blocked', 'completed', 'cancelled'],
            'in_progress' => ['blocked', 'completed', 'cancelled'],
            'blocked' => ['planned', 'in_progress', 'cancelled'],
            'completed' => [],
            'cancelled' => ['planned'],
        ], JSON_THROW_ON_ERROR);
        DB::table('work_orders')->update(['allowed_transitions_snapshot' => $defaults]);
        DB::table('work_order_templates')
            ->whereNotNull('allowed_transitions')
            ->orderBy('id')
            ->each(function (object $template): void {
                $transitions = is_string($template->allowed_transitions)
                    ? $template->allowed_transitions
                    : json_encode($template->allowed_transitions, JSON_THROW_ON_ERROR);
                DB::table('work_orders')
                    ->where('work_order_template_id', $template->id)
                    ->update(['allowed_transitions_snapshot' => $transitions]);
            });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn('allowed_transitions_snapshot');
        });
    }
};
