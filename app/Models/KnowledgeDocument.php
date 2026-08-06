<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeDocument extends Model
{
    protected $fillable = [
        'user_id', 'title', 'area', 'allowed_roles', 'confidentiality', 'status', 'failure_reason',
        'source', 'source_url', 'original_filename', 'file_size', 'content_hash', 'version',
        'embedding_model', 'indexed_at', 'stale_at',
    ];

    protected function casts(): array
    {
        return ['allowed_roles' => 'array', 'indexed_at' => 'datetime', 'stale_at' => 'datetime'];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeDocumentVersion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
