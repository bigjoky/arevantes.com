<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Opportunity extends Model
{
    protected $fillable = [
        'person_id',
        'installation_id',
        'owner_user_id',
        'created_by',
        'code',
        'title',
        'stage',
        'probability',
        'estimated_value',
        'expected_close_on',
        'source',
        'lost_reason',
        'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function quote(): HasOne
    {
        return $this->hasOne(Quote::class);
    }

    protected function casts(): array
    {
        return [
            'probability' => 'integer',
            'estimated_value' => 'decimal:2',
            'expected_close_on' => 'date',
        ];
    }
}
