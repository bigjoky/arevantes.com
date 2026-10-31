<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalVehicle extends Model
{
    protected $fillable = ['registration', 'registration_key', 'name', 'make', 'model', 'type', 'status', 'base_location', 'notes', 'created_by'];

    public function blocks(): HasMany
    {
        return $this->hasMany(OperationalVehicleBlock::class, 'vehicle_id');
    }
}
