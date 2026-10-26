<?php

namespace App\Services\Documents;

use App\Models\Opportunity;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use LogicException;

class CommercialNumberReservation
{
    // The caller holds the opportunity counter or quote series lock until its
    // document is committed. A failed creation also rolls back consumption.
    public function consume(string $type, int $year, string $prefix, int $padding = 5): ?string
    {
        if (DB::transactionLevel() === 0 || ! in_array($type, ['opportunity', 'quote'], true)) {
            throw new LogicException('Commercial reservations require a supported type and a transaction.');
        }

        $reservation = DB::table('commercial_number_reservations')
            ->where('document_type', $type)->where('year', $year)
            ->whereNull('consumed_at')->orderBy('number')->lockForUpdate()->first();

        if (! $reservation) {
            return null;
        }

        $number = $prefix.'-'.$year.'-'.str_pad((string) $reservation->number, $padding, '0', STR_PAD_LEFT);
        $occupied = $type === 'opportunity'
            ? Opportunity::withTrashed()->where('code', $number)->exists()
            : Quote::where('number', $number)->exists();

        if ($occupied) {
            throw new LogicException('The reserved commercial number is already occupied: '.$number);
        }

        DB::table('commercial_number_reservations')->where('id', $reservation->id)->update(['consumed_at' => now()]);

        return $number;
    }
}
