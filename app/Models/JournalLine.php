<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class JournalLine extends Model
{
    protected static function booted(): void
    {
        $protect = function (self $line) {
            if ($line->entry()->where('status', 'posted')->exists()) {
                throw ValidationException::withMessages(['lines' => __('Posted journal lines are immutable; use a reversal.')]);
            }
        };
        static::updating($protect);
        static::deleting($protect);
    }

    protected $fillable = ['ledger_account_id', 'description', 'debit', 'credit'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }
}
