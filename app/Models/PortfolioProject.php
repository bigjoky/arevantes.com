<?php

namespace App\Models;

use App\Casts\PreciseInstant;
use App\Models\Concerns\HasPreciseInstants;
use App\Support\Time\BusinessTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortfolioProject extends Model
{
    use HasPreciseInstants;

    protected $fillable = [
        'installation_id',
        'created_by',
        'updated_by',
        'title',
        'slug',
        'service_slug',
        'location',
        'summary',
        'description',
        'completed_on',
        'is_featured',
        'sort_order',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_on' => 'date',
            'is_featured' => 'boolean',
            'published_at' => PreciseInstant::class.':latest',
        ];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(PortfolioProjectImage::class)->orderBy('position')->orderBy('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->whereInstant('published_at', '<=', BusinessTime::now());
    }
}
