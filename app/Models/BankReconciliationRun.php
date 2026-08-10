<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliationRun extends Model
{
    protected $fillable = ['bank_account_id', 'triggered_by', 'status', 'minimum_confidence', 'matched_count', 'skipped_count', 'failed_count', 'summary', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['minimum_confidence' => 'integer', 'summary' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BankReconciliationRunItem::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
