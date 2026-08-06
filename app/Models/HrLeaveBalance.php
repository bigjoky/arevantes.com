<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeaveBalance extends Model
{
    protected $fillable = ['person_id', 'policy_id', 'year', 'carryover_days', 'adjustment_days', 'notes'];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(HrLeavePolicy::class, 'policy_id');
    }

    protected function casts(): array
    {
        return ['carryover_days' => 'decimal:2', 'adjustment_days' => 'decimal:2'];
    }
}
