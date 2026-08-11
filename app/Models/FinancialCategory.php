<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialCategory extends Model
{
    protected $fillable = ['code', 'name', 'reporting_group', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(FinancialBudget::class);
    }

    public function forecastItems(): HasMany
    {
        return $this->hasMany(FinancialForecastItem::class);
    }

    public function ledgerAccounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class);
    }
}
