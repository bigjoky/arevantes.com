<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeDocumentVersion extends Model
{
    protected $fillable = ['knowledge_document_id', 'version', 'content_hash', 'content', 'created_by', 'indexed_at'];

    protected function casts(): array
    {
        return ['indexed_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }
}
