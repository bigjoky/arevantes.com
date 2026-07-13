<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementImport extends Model
{
    protected $fillable = ['bank_account_id', 'imported_by', 'filename', 'file_hash', 'format', 'period_from', 'period_to', 'opening_balance', 'closing_balance', 'created_transactions', 'duplicate_transactions', 'control_valid', 'control_data'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'opening_balance' => 'decimal:2', 'closing_balance' => 'decimal:2', 'control_valid' => 'boolean', 'control_data' => 'array'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
