<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankReconciliationRule extends Model
{
    protected $fillable = ['bank_account_id', 'ledger_account_id', 'name', 'priority', 'direction', 'match_field', 'match_operator', 'pattern', 'min_amount', 'max_amount', 'confidence', 'is_active', 'application_count', 'last_applied_at', 'current_version', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['min_amount' => 'decimal:2', 'max_amount' => 'decimal:2', 'confidence' => 'integer', 'priority' => 'integer', 'is_active' => 'boolean', 'last_applied_at' => 'datetime', 'current_version' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function runItems(): HasMany
    {
        return $this->hasMany(BankReconciliationRunItem::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BankReconciliationRuleVersion::class)->latest('version');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(BankReconciliationRuleVersion::class)->latestOfMany('version');
    }
}
