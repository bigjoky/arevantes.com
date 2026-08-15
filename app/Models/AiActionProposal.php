<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AiActionProposal extends Model
{
    protected $fillable = [
        'uuid', 'ai_generation_id', 'installation_id', 'proposed_by', 'action',
        'status', 'payload', 'idempotency_key', 'confirmed_by', 'confirmed_at',
        'executed_at', 'crm_activity_id', 'result',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $proposal) => $proposal->uuid ??= (string) Str::uuid());
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(AiGeneration::class, 'ai_generation_id');
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(CrmActivity::class, 'crm_activity_id');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'confirmed_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }
}
