<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteTemplate extends Model
{
    protected $fillable = ['name', 'service_type', 'lines', 'created_by'];

    protected function casts(): array
    {
        return ['lines' => 'array'];
    }
}
