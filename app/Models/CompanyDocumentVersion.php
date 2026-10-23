<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class CompanyDocumentVersion extends Model
{
    protected $fillable = ['company_document_id', 'uploaded_by', 'version', 'path', 'original_name', 'mime_type', 'size', 'sha256'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(CompanyDocument::class, 'company_document_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected static function booted(): void
    {
        $deny = fn () => throw ValidationException::withMessages(['document' => __('Document versions cannot be overwritten or deleted.')]);
        static::updating($deny);
        static::deleting($deny);
    }
}
