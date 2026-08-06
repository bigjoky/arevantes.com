<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemHealthCheck extends Model
{
    protected $fillable = ['component', 'status', 'message', 'metrics', 'checked_at'];

    protected function casts(): array
    {
        return ['metrics' => 'array', 'checked_at' => 'datetime'];
    }
}
