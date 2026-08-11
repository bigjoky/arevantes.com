<?php

namespace App\Services\Supply;

use Illuminate\Support\Facades\DB;

class SupplyNumber
{
    public function next(string $scope, string $prefix, string $table): string
    {
        $nextFromExisting = $this->nextFromExisting($table, $prefix);
        $now = now();

        DB::table('supply_number_sequences')->insertOrIgnore([
            'scope' => $scope,
            'next_number' => $nextFromExisting,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sequence = DB::table('supply_number_sequences')
            ->where('scope', $scope)
            ->lockForUpdate()
            ->first();
        $number = (int) $sequence->next_number;

        DB::table('supply_number_sequences')
            ->where('scope', $scope)
            ->update(['next_number' => $number + 1, 'updated_at' => $now]);

        return $prefix.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    private function nextFromExisting(string $table, string $prefix): int
    {
        $lastNumber = DB::table($table)
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        return $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;
    }
}
