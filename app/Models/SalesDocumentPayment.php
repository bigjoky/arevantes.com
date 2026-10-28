<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Validation\ValidationException;

class SalesDocumentPayment extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('active_customer_payment', fn ($query) => $query->whereNull('sales_document_payments.voided_at'));
        static::updating(function (self $payment): void {
            if ($payment->getOriginal('voided_at') && $payment->isDirty()) {
                throw ValidationException::withMessages(['payment' => __('Cancelled receipts are immutable.')]);
            }
        });
        static::deleting(function (self $payment): void {
            if ($payment->voided_at) {
                throw ValidationException::withMessages(['payment' => __('Cancelled receipts are immutable.')]);
            }
        });
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function reversalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_entry_id');
    }

    public function cancellationRevision(): string
    {
        return hash('sha256', json_encode($this->only(['id', 'sales_document_id', 'paid_at', 'amount', 'method', 'reference', 'notes', 'updated_at', 'origin_bank_transaction_id', 'voided_at']), JSON_THROW_ON_ERROR));
    }

    protected $fillable = ['idempotency_key', 'created_by', 'origin_bank_transaction_id', 'paid_at', 'amount', 'method', 'reference', 'notes'];

    protected function casts(): array
    {
        return ['paid_at' => 'date:Y-m-d', 'amount' => 'decimal:2', 'voided_at' => 'immutable_datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class);
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(BankReconciliation::class);
    }

    public function accountingEntry(): MorphOne
    {
        return $this->morphOne(JournalEntry::class, 'source')->where('event', 'payment');
    }
}
