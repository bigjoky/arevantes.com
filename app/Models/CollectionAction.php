<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionAction extends Model
{
    protected $fillable = ['sales_document_id', 'assigned_to', 'status', 'resolution_reason', 'contacted_on', 'promised_on', 'promised_amount', 'next_action_on', 'resolved_at', 'notes'];

    protected function casts(): array
    {
        return ['contacted_on' => 'date', 'promised_on' => 'date', 'promised_amount' => 'decimal:2', 'next_action_on' => 'date', 'resolved_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CollectionActionEvent::class)->latest('occurred_at')->latest('id');
    }
}
