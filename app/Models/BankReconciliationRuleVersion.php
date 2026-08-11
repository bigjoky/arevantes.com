<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliationRuleVersion extends Model
{
    protected $fillable = ['bank_reconciliation_rule_id', 'version', 'snapshot', 'change_reason', 'changed_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'snapshot' => 'array'];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(BankReconciliationRule::class, 'bank_reconciliation_rule_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function postings(): HasMany
    {
        return $this->hasMany(BankTransactionPosting::class, 'reconciliation_rule_version_id');
    }
}
