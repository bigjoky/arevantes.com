<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class PurchaseDocument extends Model
{
    protected $fillable = ['type', 'source_document_id', 'installation_id', 'work_order_id', 'sales_document_id', 'procurement_batch_id', 'supplier_id', 'document_series_id', 'created_by', 'number', 'supplier_number', 'status', 'matching_status', 'invoicing_status', 'issue_date', 'quoted_at', 'valid_until', 'delivery_days', 'due_date', 'subtotal', 'tax_amount', 'shipping_cost', 'amount', 'match_variance_amount', 'paid_amount', 'notes', 'quote_notes', 'is_selected', 'locked_at', 'approved_at', 'approved_by', 'approval_notes'];

    protected function casts(): array
    {
        return ['issue_date' => 'date:Y-m-d', 'quoted_at' => 'datetime', 'valid_until' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'shipping_cost' => 'decimal:2', 'amount' => 'decimal:2', 'match_variance_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'is_selected' => 'boolean', 'locked_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_document_id');
    }

    public function derivedDocuments(): HasMany
    {
        return $this->hasMany(self::class, 'source_document_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(self::class, 'source_document_id')->where('type', 'supplier_credit_note');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseDocumentLine::class)->orderBy('position');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(GoodsReturn::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class)->orderByDesc('paid_at');
    }

    public function accountingEntry(): MorphOne
    {
        return $this->morphOne(JournalEntry::class, 'source')->where('event', 'document');
    }

    public function attachment(): HasOne
    {
        return $this->hasOne(PurchaseDocumentAttachment::class);
    }
}
