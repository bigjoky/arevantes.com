<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrContractChange extends Model
{
    protected $fillable = [
        'person_id',
        'changed_by',
        'effective_date',
        'reason',
        'previous_values',
        'new_values',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'previous_values' => 'encrypted:array',
            'new_values' => 'encrypted:array',
        ];
    }
}
