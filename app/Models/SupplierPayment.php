<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class SupplierPayment extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('active_supplier_payment', fn ($query) => $query->whereNull('supplier_payments.voided_at'));
    }

    protected $fillable = ['purchase_document_id', 'expense_id', 'idempotency_key', 'created_by', 'origin_bank_transaction_id', 'paid_at', 'amount', 'method', 'funding_source_type', 'funding_source_id', 'ledger_account_id', 'reference', 'notes'];

    protected function casts(): array
    {
        return ['paid_at' => 'date:Y-m-d', 'amount' => 'decimal:2', 'voided_at' => 'datetime'];
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function purchaseDocument(): BelongsTo
    {
        return $this->belongsTo(PurchaseDocument::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function fundingBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'funding_source_id');
    }

    public function fundingCard(): BelongsTo
    {
        return $this->belongsTo(BankFinancialProduct::class, 'funding_source_id');
    }

    public function fundingEmployee(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'funding_source_id');
    }

    public function sourceLabel(): string
    {
        return match ($this->funding_source_type) {
            'bank_account' => $this->fundingBankAccount?->name ?: $this->ledgerAccount?->name,
            'card' => ($this->fundingCard?->name ?: $this->ledgerAccount?->name).($this->fundingCard?->identifier_last_four ? ' · •••• '.$this->fundingCard->identifier_last_four : ''),
            'employee' => $this->fundingEmployee?->company_name ?: trim(($this->fundingEmployee?->first_name ?? '').' '.($this->fundingEmployee?->last_name ?? '')),
            default => $this->ledgerAccount?->name ?: __('Legacy treasury account'),
        };
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
