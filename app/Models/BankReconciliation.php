<?php

namespace App\Models;

use App\Casts\UtcTimestamp;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliation extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('active_bank_reconciliation', fn ($query) => $query->whereNull('bank_reconciliations.reversed_at'));
    }

    public function effectiveAmount(): string
    {
        return (string) BigDecimal::of($this->allocated_amount ?? $this->transaction->amount)->abs()->toScale(2);
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    protected $fillable = ['bank_transaction_id', 'sales_document_payment_id', 'supplier_payment_id', 'tax_return_payment_id', 'journal_entry_id', 'reconciled_by', 'method', 'reconciled_at', 'allocated_amount', 'confirmed_at_utc', 'operation_key', 'reason', 'reversed_at', 'reversed_by', 'reversal_reason', 'reversal_operation_key', 'reversal_entry_id'];

    protected function casts(): array
    {
        return ['reconciled_at' => 'datetime', 'allocated_amount' => 'decimal:2', 'reversed_at' => UtcTimestamp::class, 'confirmed_at_utc' => UtcTimestamp::class];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(SalesDocumentPayment::class, 'sales_document_payment_id')->withoutGlobalScope('active_customer_payment');
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class)->withoutGlobalScope('active_supplier_payment');
    }

    public function taxPayment(): BelongsTo
    {
        return $this->belongsTo(TaxReturnPayment::class, 'tax_return_payment_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
