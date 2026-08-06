<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiGeneration extends Model
{
    protected $fillable = [
        'uuid', 'installation_id', 'user_id', 'status', 'mode', 'provider', 'model',
        'prompt_version', 'prompt', 'structured_response', 'raw_response',
        'sources', 'usage', 'duration_ms', 'error', 'started_at', 'completed_at',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $generation) => $generation->uuid ??= (string) Str::uuid());
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(AiFeedback::class);
    }

    protected function casts(): array
    {
        return [
            'structured_response' => 'array',
            'sources' => 'array',
            'usage' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
