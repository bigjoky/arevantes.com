<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSeries extends Model
{
    protected $table = 'document_series';

    protected $fillable = ['document_type', 'name', 'prefix', 'next_number', 'fiscal_year', 'fiscal_year_id', 'is_active', 'last_issued_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_issued_at' => 'datetime'];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }
}
