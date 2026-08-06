<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransactionPosting extends Model
{
    protected $fillable = ['bank_transaction_id', 'counterpart_account_id', 'allocation_data', 'journal_entry_id', 'reversal_entry_id', 'posted_by', 'reversed_at'];

    protected function casts(): array
    {
        return ['allocation_data' => 'array', 'reversed_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function counterpart(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'counterpart_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function reversalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_entry_id');
    }
}
