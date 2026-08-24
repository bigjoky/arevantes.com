<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityEvent extends Model
{
    protected $fillable = ['opportunity_id', 'actor_id', 'event_type', 'summary', 'note', 'changes', 'occurred_at'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'occurred_at' => 'datetime'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
