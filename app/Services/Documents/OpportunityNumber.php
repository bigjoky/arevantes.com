<?php

namespace App\Services\Documents;

use App\Models\Opportunity;
use Illuminate\Support\Facades\DB;
use LogicException;

class OpportunityNumber
{
    public function next(int $initialNumber): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Opportunity numbers must be allocated inside the creation transaction.');
        }

        $year = (int) now()->format('Y');
        $prefix = 'OPP-'.$year.'-';

        if (! DB::table('opportunity_number_sequences')->where('year', $year)->exists()) {
            $largest = Opportunity::withTrashed()->where('code', 'like', $prefix.'%')->pluck('code')
                ->map(fn (string $code) => ctype_digit(substr($code, strlen($prefix))) ? (int) substr($code, strlen($prefix)) : 0)
                ->max() ?? 0;

            DB::table('opportunity_number_sequences')->insertOrIgnore([
                'year' => $year,
                'next_number' => max($initialNumber, $largest + 1),
            ]);
        }

        $sequence = DB::table('opportunity_number_sequences')->where('year', $year)->lockForUpdate()->first();
        $reserved = app(CommercialNumberReservation::class)->consume('opportunity', $year, 'OPP');

        if ($reserved !== null) {
            return $reserved;
        }

        $next = (int) $sequence->next_number;
        do {
            $code = $prefix.str_pad((string) $next++, 5, '0', STR_PAD_LEFT);
        } while (Opportunity::withTrashed()->where('code', $code)->exists());

        DB::table('opportunity_number_sequences')->where('year', $year)->update(['next_number' => $next]);

        return $code;
    }
}
