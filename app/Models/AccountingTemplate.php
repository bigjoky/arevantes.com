<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingTemplate extends Model
{
    protected $fillable = ['name', 'description', 'is_system', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingTemplateLine::class)->orderBy('position');
    }
}
