<?php

// Run from an already bootstrapped production Tinker session. This operation
// reserves only the two numbers explicitly released on 26 October 2026.
use App\Models\DocumentSeries;
use App\Models\FiscalYear;
use App\Models\Opportunity;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

if (! app()->environment('production') || DB::getDriverName() !== 'pgsql') {
    throw new RuntimeException('This operation is restricted to production PostgreSQL.');
}

$result = DB::transaction(function (): array {
    DB::statement("SET LOCAL lock_timeout = '5s'");
    $year = FiscalYear::where('year', 2026)->lockForUpdate()->firstOrFail();
    $series = DocumentSeries::where('fiscal_year_id', $year->id)->where('document_type', 'quote')->where('is_active', true)->lockForUpdate()->firstOrFail();
    if ($year->status !== 'open' || $series->prefix !== 'PRE') {
        throw new RuntimeException('Unexpected fiscal year or quote series.');
    }

    DB::statement('LOCK TABLE opportunity_number_sequences IN SHARE ROW EXCLUSIVE MODE');

    if (Opportunity::withTrashed()->where('code', 'OPP-2026-00010')->exists()
        || Quote::where('number', 'PRE-2026-00006')->exists()) {
        throw new RuntimeException('One of the released numbers is occupied.');
    }

    foreach (['opportunity' => 10, 'quote' => 6] as $type => $number) {
        $key = ['document_type' => $type, 'year' => 2026, 'number' => $number];
        $existing = DB::table('commercial_number_reservations')->where($key)->first();
        if ($existing?->consumed_at !== null) {
            throw new RuntimeException('The reservation was already consumed; it must not be rearmed.');
        }
        if (! $existing) {
            DB::table('commercial_number_reservations')->insert([
                ...$key,
                'reason' => 'Reutilización única autorizada tras eliminar el duplicado OPP-2026-00010 y su presupuesto PRE-2026-00006 el 26/10/2026.',
                'created_at' => now(),
            ]);
        }
    }

    return [
        'environment' => app()->environment(),
        'reservations' => DB::table('commercial_number_reservations')->where('year', 2026)->get(),
        'normal_quote_next_number' => $series->next_number,
        'opportunity_counter' => DB::table('opportunity_number_sequences')->where('year', 2026)->first(),
    ];
});

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
