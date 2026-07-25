<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionActionEvent extends Model
{
    protected $fillable = ['collection_action_id', 'event_type', 'status_from', 'status_to', 'promised_on', 'promised_amount', 'next_action_on', 'notes', 'occurred_at', 'created_by'];

    protected function casts(): array
    {
        return ['promised_on' => 'date', 'promised_amount' => 'decimal:2', 'next_action_on' => 'date', 'occurred_at' => 'datetime'];
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(CollectionAction::class, 'collection_action_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
