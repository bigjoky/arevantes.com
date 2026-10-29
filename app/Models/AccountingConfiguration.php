<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingConfiguration extends Model
{
    protected $fillable = ['id', 'defaults', 'rules', 'templates', 'revision', 'updated_by'];

    protected function casts(): array
    {
        return ['defaults' => 'array', 'rules' => 'array', 'templates' => 'array'];
    }
}
