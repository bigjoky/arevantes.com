<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentDelivery extends Model
{
    protected $fillable = ['quote_id', 'sales_document_id', 'sent_by', 'recipient_email', 'token_hash', 'status', 'document_version', 'document_hash', 'expires_at', 'sent_at', 'first_viewed_at', 'last_downloaded_at', 'responded_at', 'revoked_at', 'revoked_by', 'response_ip', 'response_user_agent', 'failure_reason'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'sent_at' => 'datetime', 'first_viewed_at' => 'datetime', 'last_downloaded_at' => 'datetime', 'responded_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function salesDocument(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class);
    }
}
