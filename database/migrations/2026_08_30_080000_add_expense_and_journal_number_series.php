<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (DB::table('fiscal_years')->get(['id', 'year']) as $year) {
            foreach ([
                'expense' => ['prefix' => 'EXP', 'table' => 'expenses', 'date' => 'expense_date'],
                'journal_entry' => ['prefix' => 'AST', 'table' => 'journal_entries', 'date' => 'entry_date'],
                'payment_batch' => ['prefix' => 'PAY', 'table' => 'payment_batches', 'date' => 'planned_on'],
            ] as $type => $definition) {
                DB::table('document_series')->insertOrIgnore([
                    'document_type' => $type,
                    'name' => $definition['prefix'].' '.$year->year,
                    'prefix' => $definition['prefix'],
                    'next_number' => $this->nextNumber($definition['table'], $definition['date'], (int) $year->year),
                    'fiscal_year' => $year->year,
                    'fiscal_year_id' => $year->id,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('document_series')->whereIn('document_type', ['expense', 'journal_entry', 'payment_batch'])->delete();
    }

    private function nextNumber(string $table, string $dateColumn, int $year): int
    {
        $lastNumber = DB::table($table)
            ->whereYear($dateColumn, $year)
            ->orderByDesc('number')
            ->value('number');

        return $lastNumber ? ((int) substr($lastNumber, strrpos($lastNumber, '-') + 1)) + 1 : 1;
    }
};
