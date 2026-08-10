<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentBatchItem extends Model
{
    protected $fillable = ['payment_batch_id', 'payable_type', 'payable_id', 'supplier_payment_id', 'amount', 'recipient_name', 'recipient_iban', 'recipient_bic', 'payment_reference'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class, 'payment_batch_id');
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
    }
}
