<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderAttachment extends Model
{
    protected $touches = ['workOrder'];

    protected $fillable = ['uploaded_by', 'kind', 'evidence_key', 'document_version', 'customer_visible', 'publication_status', 'published_at', 'reviewed_by', 'reviewed_at', 'published_by', 'supersedes_attachment_id', 'original_name', 'path', 'mime_type', 'size', 'caption'];

    protected function casts(): array
    {
        return ['customer_visible' => 'boolean', 'published_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function supersededAttachment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_attachment_id');
    }
}
