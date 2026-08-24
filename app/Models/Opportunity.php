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
        'updated_by',
        'parent_opportunity_id',
        'code',
        'title',
        'commercial_kind',
        'recurrence_interval',
        'renewal_due_on',
        'stage',
        'probability',
        'commercial_score',
        'first_response_due_at',
        'first_responded_at',
        'first_response_sla_alerted_at',
        'first_response_sla_escalated_at',
        'estimated_value',
        'expected_close_on',
        'source',
        'source_detail',
        'lost_reason',
        'lost_reason_detail',
        'notes',
    ];

    public ?string $stageChangeReason = null;

    public ?int $stageChangeActorId = null;

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function parentOpportunity(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_opportunity_id')->withTrashed();
    }

    public function followUpOpportunities(): HasMany
    {
        return $this->hasMany(self::class, 'parent_opportunity_id');
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

    public function events(): HasMany
    {
        return $this->hasMany(OpportunityEvent::class)->latest('occurred_at');
    }

    protected function casts(): array
    {
        return [
            'probability' => 'integer',
            'commercial_score' => 'integer',
            'first_response_due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'first_response_sla_alerted_at' => 'datetime',
            'first_response_sla_escalated_at' => 'datetime',
            'estimated_value' => 'decimal:2',
            'expected_close_on' => 'date',
            'renewal_due_on' => 'date',
        ];
    }
}
