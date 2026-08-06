<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingRecurringRun extends Model
{
    protected $fillable = [
        'accounting_template_id',
        'scheduled_on',
        'amount',
        'journal_entry_id',
        'reversal_due_on',
        'reversal_entry_id',
        'status',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'amount' => 'decimal:2',
            'reversal_due_on' => 'date',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AccountingTemplate::class, 'accounting_template_id');
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
