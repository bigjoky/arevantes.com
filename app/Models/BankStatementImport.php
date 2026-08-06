<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementImport extends Model
{
    protected $fillable = ['bank_account_id', 'imported_by', 'rolled_back_by', 'filename', 'file_hash', 'format', 'period_from', 'period_to', 'opening_balance', 'closing_balance', 'created_transactions', 'duplicate_transactions', 'control_valid', 'control_data', 'rolled_back_at', 'rollback_reason'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'opening_balance' => 'decimal:2', 'closing_balance' => 'decimal:2', 'control_valid' => 'boolean', 'control_data' => 'array', 'rolled_back_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function rolledBackBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rolled_back_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
