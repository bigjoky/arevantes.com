<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentBatch extends Model
{
    protected $fillable = ['number', 'bank_account_id', 'planned_on', 'status', 'created_by', 'approved_by', 'executed_by', 'approved_at', 'exported_at', 'executed_at', 'reconciled_at'];

    protected function casts(): array
    {
        return ['planned_on' => 'date', 'approved_at' => 'datetime', 'exported_at' => 'datetime', 'executed_at' => 'datetime', 'reconciled_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PaymentBatchItem::class);
    }
}
