<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxReturn extends Model
{
    protected $fillable = ['tax_type', 'year', 'quarter', 'period_from', 'period_to', 'status', 'settlement_status', 'output_vat', 'input_vat', 'result', 'result_option', 'paid_amount', 'snapshot', 'journal_entry_id', 'prepared_by', 'prepared_at', 'filing_reference', 'filed_on', 'filed_by', 'receipt_path', 'receipt_original_name', 'receipt_sha256'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'output_vat' => 'decimal:2', 'input_vat' => 'decimal:2', 'result' => 'decimal:2', 'paid_amount' => 'decimal:2', 'snapshot' => 'array', 'prepared_at' => 'datetime', 'filed_on' => 'date'];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TaxReturnPayment::class)->orderByDesc('paid_on');
    }
}
