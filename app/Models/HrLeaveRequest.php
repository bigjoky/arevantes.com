<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrLeaveRequest extends Model
{
    protected $fillable = [
        'person_id',
        'substitute_person_id',
        'type',
        'start_date',
        'end_date',
        'requested_days',
        'status',
        'notes',
        'decision_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'substitute_person_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HrLeaveAttachment::class, 'leave_request_id');
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }
}
