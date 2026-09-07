<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineeringScheduleProposal extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['dossier_version' => 'integer', 'estimated_hours' => 'decimal:2', 'proposed_on' => 'date:Y-m-d', 'reviewed_at' => 'datetime', 'executed_at' => 'datetime'];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function contentFingerprint(): string
    {
        return hash('sha256', json_encode($this->only(['engineering_dossier_id', 'dossier_version', 'proposed_on', 'assignee_id', 'estimated_hours', 'note', 'created_by']), JSON_THROW_ON_ERROR));
    }
}
