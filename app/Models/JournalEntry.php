<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;

class JournalEntry extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $entry) {
            if ($entry->getOriginal('status') === 'posted' && $entry->isDirty()) {
                throw ValidationException::withMessages(['entry' => __('Posted journal entries are immutable; use a reversal.')]);
            }
        });
        static::deleting(function (self $entry) {
            if ($entry->status === 'posted') {
                throw ValidationException::withMessages(['entry' => __('Posted journal entries are immutable; use a reversal.')]);
            }
        });
    }

    protected $fillable = ['fiscal_year_id', 'created_by', 'entry_date', 'number', 'description', 'source_type', 'source_id', 'event', 'entry_kind', 'reverses_entry_id', 'status', 'posted_at'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'posted_at' => 'datetime'];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScope('active_supplier_payment');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_entry_id');
    }
}
