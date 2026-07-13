<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseMailboxMessage extends Model
{
    protected $fillable = [
        'message_uid', 'message_id', 'subject', 'from_name', 'from_email', 'received_at',
        'status', 'attachment_count', 'imported_count', 'error', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(PurchaseMailbox::class, 'purchase_mailbox_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PurchaseInboxDocument::class);
    }
}
