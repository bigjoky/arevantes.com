<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    protected $fillable = ['code', 'name', 'type', 'parent_code', 'level', 'is_postable', 'is_active', 'source', 'source_version'];

    protected function casts(): array
    {
        return ['is_postable' => 'boolean', 'is_active' => 'boolean'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
