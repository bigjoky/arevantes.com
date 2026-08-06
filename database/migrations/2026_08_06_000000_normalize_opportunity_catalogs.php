<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('source_detail')->nullable()->after('source');
            $table->text('lost_reason_detail')->nullable()->after('lost_reason');
            $table->index('source');
            $table->index('lost_reason');
        });

        $sourceAliases = [
            'website' => 'website',
            'web' => 'website',
            'referral' => 'referral',
            'referido' => 'referral',
            'campaign' => 'campaign',
            'campaña' => 'campaign',
            'recurring_customer' => 'recurring_customer',
            'cliente recurrente' => 'recurring_customer',
            'outbound' => 'outbound',
            'prospección comercial' => 'outbound',
            'partner' => 'partner',
            'colaborador' => 'partner',
            'other' => 'other',
            'otro' => 'other',
        ];
        $lostReasonAliases = [
            'price' => 'price',
            'precio' => 'price',
            'competitor' => 'competitor',
            'competencia' => 'competitor',
            'timing' => 'timing',
            'plazo' => 'timing',
            'financing' => 'financing',
            'financiación' => 'financing',
            'no_response' => 'no_response',
            'sin respuesta' => 'no_response',
            'scope' => 'scope',
            'alcance' => 'scope',
            'duplicate' => 'duplicate',
            'duplicada' => 'duplicate',
            'other' => 'other',
            'otro' => 'other',
        ];

        DB::table('opportunities')
            ->select(['id', 'source', 'lost_reason'])
            ->orderBy('id')
            ->chunkById(500, function ($opportunities) use ($sourceAliases, $lostReasonAliases): void {
                foreach ($opportunities as $opportunity) {
                    $updates = [];

                    if ($opportunity->source) {
                        $normalized = mb_strtolower(trim($opportunity->source));
                        $updates['source'] = $sourceAliases[$normalized] ?? 'other';
                        $updates['source_detail'] = isset($sourceAliases[$normalized]) ? null : $opportunity->source;
                    }

                    if ($opportunity->lost_reason) {
                        $normalized = mb_strtolower(trim($opportunity->lost_reason));
                        $updates['lost_reason'] = $lostReasonAliases[$normalized] ?? 'other';
                        $updates['lost_reason_detail'] = isset($lostReasonAliases[$normalized]) ? null : $opportunity->lost_reason;
                    }

                    if ($updates !== []) {
                        DB::table('opportunities')->where('id', $opportunity->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('opportunities')
            ->where('source', 'other')
            ->whereNotNull('source_detail')
            ->update(['source' => DB::raw('source_detail')]);
        DB::table('opportunities')
            ->where('lost_reason', 'other')
            ->whereNotNull('lost_reason_detail')
            ->update(['lost_reason' => DB::raw('lost_reason_detail')]);

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropIndex(['lost_reason']);
            $table->dropColumn(['source_detail', 'lost_reason_detail']);
        });
    }
};
