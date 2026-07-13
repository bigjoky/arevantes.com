<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends Model
{
    protected $fillable = ['year', 'starts_on', 'ends_on', 'status', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'closed_at' => 'datetime'];
    }

    public function series(): HasMany
    {
        return $this->hasMany(DocumentSeries::class);
    }
}
