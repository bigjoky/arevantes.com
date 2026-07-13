<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    protected $fillable = [
        'ai_conversation_id', 'role', 'status', 'content', 'page_url', 'sources',
        'provider', 'model', 'rating', 'feedback_notes', 'duration_ms', 'error',
    ];

    protected function casts(): array
    {
        return ['sources' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
