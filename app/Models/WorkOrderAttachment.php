<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderAttachment extends Model
{
    protected $touches = ['workOrder'];

    protected $fillable = ['uploaded_by', 'kind', 'evidence_key', 'customer_visible', 'published_at', 'original_name', 'path', 'mime_type', 'size', 'caption'];

    protected function casts(): array
    {
        return ['customer_visible' => 'boolean', 'published_at' => 'datetime'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
