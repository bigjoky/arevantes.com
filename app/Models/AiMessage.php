<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    protected $fillable = [
        'ai_conversation_id', 'role', 'status', 'content', 'page_url', 'context_module',
        'prompt', 'sources', 'source_context', 'provider', 'model', 'usage', 'cost_micros',
        'automated_score', 'human_score', 'reviewed_by', 'reviewed_at', 'rating',
        'feedback_notes', 'duration_ms', 'error',
    ];

    protected function casts(): array
    {
        return [
            'sources' => 'array', 'source_context' => 'array', 'usage' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
