<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingTemplate extends Model
{
    protected $fillable = ['name', 'description', 'is_system', 'is_active', 'recurring_amount', 'recurrence', 'next_run_on', 'end_on', 'reversal_days', 'last_run_on', 'created_by'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'recurring_amount' => 'decimal:2',
            'next_run_on' => 'date',
            'end_on' => 'date',
            'last_run_on' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingTemplateLine::class)->orderBy('position');
    }

    public function recurringRuns(): HasMany
    {
        return $this->hasMany(AccountingRecurringRun::class);
    }

    /** @return array<int, array<string, mixed>> */
    public function journalLines(float $amount): array
    {
        $this->loadMissing('lines');
        $lines = $this->lines->map(fn ($line) => [
            'account_code' => $line->account_code,
            'description' => $line->description,
            'debit' => $line->side === 'debit' ? round($amount * $line->percentage / 100, 2) : 0,
            'credit' => $line->side === 'credit' ? round($amount * $line->percentage / 100, 2) : 0,
        ])->all();

        foreach (['debit', 'credit'] as $side) {
            $difference = round($amount - collect($lines)->sum($side), 2);
            $index = collect($lines)->search(fn ($line) => $line[$side] > 0);
            if ($index !== false) {
                $lines[$index][$side] = round($lines[$index][$side] + $difference, 2);
            }
        }

        return $lines;
    }
}
