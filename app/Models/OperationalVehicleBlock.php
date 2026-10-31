<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalVehicleBlock extends Model
{
    protected $fillable = ['vehicle_id', 'starts_at_utc', 'ends_at_utc', 'reason', 'created_by', 'cancelled_at_utc', 'cancelled_by', 'cancellation_reason'];

    protected function casts(): array
    {
        return ['starts_at_utc' => 'immutable_datetime', 'ends_at_utc' => 'immutable_datetime', 'cancelled_at_utc' => 'immutable_datetime'];
    }
}
