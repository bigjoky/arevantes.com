<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationRunItem extends Model
{
    protected $fillable = ['bank_reconciliation_run_id', 'bank_reconciliation_rule_id', 'bank_transaction_id', 'bank_transaction_posting_id', 'confidence', 'outcome', 'explanation', 'error'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(BankReconciliationRun::class, 'bank_reconciliation_run_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(BankReconciliationRule::class, 'bank_reconciliation_rule_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function posting(): BelongsTo
    {
        return $this->belongsTo(BankTransactionPosting::class, 'bank_transaction_posting_id');
    }
}
