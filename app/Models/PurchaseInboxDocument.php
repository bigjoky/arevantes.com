<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInboxDocument extends Model
{
    protected $fillable = [
        'supplier_id', 'purchase_order_id', 'purchase_document_id', 'purchase_mailbox_message_id', 'uploaded_by', 'processed_by',
        'classification', 'status', 'source', 'original_name', 'path', 'mime_type', 'size', 'file_hash',
        'extraction_status', 'extracted_data', 'extraction_error', 'notes', 'extracted_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['extracted_data' => 'array', 'extracted_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseDocument::class, 'purchase_order_id');
    }

    public function purchaseDocument(): BelongsTo
    {
        return $this->belongsTo(PurchaseDocument::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function mailboxMessage(): BelongsTo
    {
        return $this->belongsTo(PurchaseMailboxMessage::class, 'purchase_mailbox_message_id');
    }
}
