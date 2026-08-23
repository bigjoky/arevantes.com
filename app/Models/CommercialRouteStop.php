<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialRouteStop extends Model
{
    protected $fillable = [
        'commercial_route_plan_id',
        'crm_activity_id',
        'person_id',
        'position',
        'status',
        'title',
        'address',
        'latitude',
        'longitude',
        'planned_at',
        'arrived_at',
        'completed_at',
        'arrival_latitude',
        'arrival_longitude',
        'arrival_accuracy_meters',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'planned_at' => 'datetime',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
            'arrival_latitude' => 'float',
            'arrival_longitude' => 'float',
            'arrival_accuracy_meters' => 'float',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CommercialRoutePlan::class, 'commercial_route_plan_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(CrmActivity::class, 'crm_activity_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id')->withTrashed();
    }
}
