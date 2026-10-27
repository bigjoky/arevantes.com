<?php

namespace App\Models;

use App\Casts\PreciseInstant;
use App\Models\Concerns\HasPreciseInstants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentDelivery extends Model
{
    use HasPreciseInstants;

    protected $fillable = ['quote_id', 'sales_document_id', 'sent_by', 'recipient_email', 'token_hash', 'status', 'document_version', 'document_hash', 'expires_at', 'sent_at', 'first_viewed_at', 'last_downloaded_at', 'responded_at', 'revoked_at', 'revoked_by', 'response_ip', 'response_user_agent', 'failure_reason'];

    protected function casts(): array
    {
        return ['expires_at' => PreciseInstant::class, 'sent_at' => PreciseInstant::class, 'first_viewed_at' => PreciseInstant::class, 'last_downloaded_at' => PreciseInstant::class, 'responded_at' => PreciseInstant::class, 'revoked_at' => PreciseInstant::class];
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
