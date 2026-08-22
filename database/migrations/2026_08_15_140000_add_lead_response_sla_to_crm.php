<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_SLA_MINUTES = 240;

    public function up(): void
    {
        Schema::table('crm_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('lead_response_sla_minutes')
                ->default(self::DEFAULT_SLA_MINUTES)
                ->after('stale_after_days');
        });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->timestamp('first_response_due_at')->nullable()->after('commercial_score');
            $table->timestamp('first_responded_at')->nullable()->after('first_response_due_at');
        });

        DB::table('opportunities')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($opportunities): void {
                $responses = DB::table('crm_activities')
                    ->whereIn('opportunity_id', $opportunities->pluck('id'))
                    ->whereIn('type', ['call', 'email', 'meeting', 'visit'])
                    ->whereIn('status', ['in_progress', 'completed'])
                    ->whereNull('deleted_at')
                    ->selectRaw('opportunity_id, min(coalesce(completed_at, updated_at)) as first_responded_at')
                    ->groupBy('opportunity_id')
                    ->pluck('first_responded_at', 'opportunity_id');

                foreach ($opportunities as $opportunity) {
                    DB::table('opportunities')->where('id', $opportunity->id)->update([
                        'first_response_due_at' => Carbon::parse($opportunity->created_at)
                            ->addMinutes(self::DEFAULT_SLA_MINUTES),
                        'first_responded_at' => $responses->get($opportunity->id),
                    ]);
                }
            });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->index(
                ['first_responded_at', 'deleted_at', 'first_response_due_at'],
                'opportunities_response_sla_lookup_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropIndex('opportunities_response_sla_lookup_idx');
            $table->dropColumn(['first_response_due_at', 'first_responded_at']);
        });

        Schema::table('crm_settings', function (Blueprint $table): void {
            $table->dropColumn('lead_response_sla_minutes');
        });
    }
};
