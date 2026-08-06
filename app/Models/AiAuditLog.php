<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAuditLog extends Model
{
    protected $fillable = ['ai_message_id', 'user_id', 'event', 'context_module', 'prompt', 'sources', 'response', 'action', 'confirmed_at', 'metadata'];

    protected function casts(): array
    {
        return ['sources' => 'array', 'metadata' => 'array', 'confirmed_at' => 'datetime'];
    }
}
