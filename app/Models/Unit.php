<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['code', 'name', 'symbol', 'decimal_places', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
