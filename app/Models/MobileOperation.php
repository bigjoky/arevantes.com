<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileOperation extends Model
{
    protected $fillable = ['user_id', 'key', 'method', 'path', 'response_status', 'response_body'];

    protected function casts(): array
    {
        return ['response_body' => 'array'];
    }
}
