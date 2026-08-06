<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankTransaction extends Model
{
    protected $fillable = ['bank_account_id', 'bank_statement_import_id', 'booked_on', 'value_on', 'amount', 'currency', 'reference', 'description', 'import_hash', 'status', 'raw_data'];

    protected function casts(): array
    {
        return ['booked_on' => 'date', 'value_on' => 'date', 'amount' => 'decimal:2', 'raw_data' => 'array'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(BankReconciliation::class);
    }

    public function posting(): HasOne
    {
        return $this->hasOne(BankTransactionPosting::class)->latestOfMany();
    }

    public function outgoingTransfer(): HasOne
    {
        return $this->hasOne(BankTransfer::class, 'outgoing_transaction_id')->latestOfMany();
    }

    public function incomingTransfer(): HasOne
    {
        return $this->hasOne(BankTransfer::class, 'incoming_transaction_id')->latestOfMany();
    }
}
