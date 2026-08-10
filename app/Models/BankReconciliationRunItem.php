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
}
