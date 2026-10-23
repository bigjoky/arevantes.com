<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = ['code', 'name', 'address', 'postal_code', 'city', 'is_usable', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return ['is_usable' => 'boolean', 'is_default' => 'boolean', 'is_active' => 'boolean'];
    }
}
