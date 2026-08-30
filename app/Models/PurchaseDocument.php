<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class PurchaseDocument extends Model
{
    protected $fillable = ['planning_scenario_request_id', 'type', 'source_document_id', 'installation_id', 'work_order_id', 'sales_document_id', 'procurement_batch_id', 'supplier_id', 'destination_warehouse_id', 'document_series_id', 'created_by', 'assigned_to', 'assigned_by', 'assigned_at', 'number', 'supplier_number', 'status', 'matching_status', 'invoicing_status', 'issue_date', 'quoted_at', 'valid_until', 'delivery_days', 'due_date', 'expected_delivery_date', 'supplier_confirmed_delivery_date', 'supplier_confirmed_at', 'supplier_confirmed_by', 'supplier_confirmation_reference', 'last_followed_up_at', 'last_followed_up_by', 'last_follow_up_note', 'next_follow_up_date', 'sent_at', 'subtotal', 'tax_amount', 'shipping_cost', 'amount', 'match_variance_amount', 'paid_amount', 'notes', 'quote_notes', 'is_selected', 'locked_at', 'approved_at', 'approved_by', 'approval_notes', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'closed_at', 'closed_by', 'closing_reason'];

    protected function casts(): array
    {
        return ['issue_date' => 'date:Y-m-d', 'assigned_at' => 'datetime', 'quoted_at' => 'datetime', 'valid_until' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d', 'expected_delivery_date' => 'date:Y-m-d', 'supplier_confirmed_delivery_date' => 'date:Y-m-d', 'supplier_confirmed_at' => 'datetime', 'last_followed_up_at' => 'datetime', 'next_follow_up_date' => 'date:Y-m-d', 'sent_at' => 'datetime', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'shipping_cost' => 'decimal:2', 'amount' => 'decimal:2', 'match_variance_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'is_selected' => 'boolean', 'locked_at' => 'datetime', 'approved_at' => 'datetime', 'cancelled_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
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

    public function supplierConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_confirmed_by');
    }

    public function lastFollowedUpBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_followed_up_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PurchaseDocumentEvent::class)->latest('occurred_at');
    }

    public function orderApprovals(): HasMany
    {
        return $this->hasMany(PurchaseOrderApproval::class)->orderBy('id');
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

    public function qualityIncidents(): HasMany
    {
        return $this->hasMany(SupplierQualityIncident::class)->latest();
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
