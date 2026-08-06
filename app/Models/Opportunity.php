<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'person_id',
        'installation_id',
        'owner_user_id',
        'created_by',
        'code',
        'title',
        'stage',
        'probability',
        'estimated_value',
        'expected_close_on',
        'source',
        'source_detail',
        'lost_reason',
        'lost_reason_detail',
        'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id')->withTrashed();
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function quote(): HasOne
    {
        return $this->hasOne(Quote::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    public function nextActivity(): HasOne
    {
        return $this->hasOne(CrmActivity::class)
            ->ofMany(
                ['due_at' => 'min', 'id' => 'min'],
                fn ($query) => $query
                    ->whereNotNull('due_at')
                    ->whereIn('status', ['pending', 'in_progress']),
            );
    }

    public function stageHistory(): HasMany
    {
        return $this->hasMany(OpportunityStageHistory::class)->latest('changed_at');
    }

    protected function casts(): array
    {
        return [
            'probability' => 'integer',
            'estimated_value' => 'decimal:2',
            'expected_close_on' => 'date',
        ];
    }
}
