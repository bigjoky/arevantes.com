<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialVisit extends Model
{
    protected $fillable = [
        'client_uuid',
        'user_id',
        'person_id',
        'opportunity_id',
        'commercial_route_plan_id',
        'prospect_type',
        'outcome',
        'visited_at',
        'latitude',
        'longitude',
        'accuracy_meters',
        'address',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_meters' => 'float',
        ];
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id')->withTrashed();
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class)->withTrashed();
    }
}
