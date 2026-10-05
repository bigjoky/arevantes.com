<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseFiscalClassification extends Model
{
    protected $fillable = ['purchase_document_id', 'operation_class', 'evidence_fingerprint', 'evidence_snapshot', 'reviewed_by', 'reviewed_at', 'reason', 'source_reference'];

    protected function casts(): array
    {
        return ['evidence_snapshot' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
