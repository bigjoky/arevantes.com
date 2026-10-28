<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TaxReturnPayment extends Model
{
    protected $fillable = ['tax_return_id', 'created_by', 'journal_entry_id', 'paid_on', 'amount', 'method', 'reference', 'notes'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function taxReturn(): BelongsTo
    {
        return $this->belongsTo(TaxReturn::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class);
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(BankReconciliation::class);
    }
}
