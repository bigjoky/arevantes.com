<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliation extends Model
{
    protected $fillable = ['bank_transaction_id', 'sales_document_payment_id', 'supplier_payment_id', 'tax_return_payment_id', 'journal_entry_id', 'reconciled_by', 'method', 'reconciled_at'];

    protected function casts(): array
    {
        return ['reconciled_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(SalesDocumentPayment::class, 'sales_document_payment_id');
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
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
