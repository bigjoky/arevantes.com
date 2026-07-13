<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransfer extends Model
{
    protected $fillable = ['outgoing_transaction_id', 'incoming_transaction_id', 'journal_entry_id', 'reversal_entry_id', 'created_by', 'reversed_at'];

    protected function casts(): array
    {
        return ['reversed_at' => 'datetime'];
    }

    public function outgoing(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'outgoing_transaction_id');
    }

    public function incoming(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'incoming_transaction_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
