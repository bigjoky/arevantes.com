<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseDocumentAttachment extends Model
{
    protected $fillable = ['uploaded_by', 'verified_by', 'original_name', 'path', 'mime_type', 'size', 'file_hash', 'extraction_status', 'extracted_data', 'extraction_error', 'extracted_at', 'verified_at'];

    protected function casts(): array
    {
        return ['extracted_data' => 'array', 'extracted_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function purchaseDocument(): BelongsTo
    {
        return $this->belongsTo(PurchaseDocument::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
