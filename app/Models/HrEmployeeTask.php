<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrEmployeeTask extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'person_id',
        'created_by',
        'completed_by',
        'title',
        'category',
        'priority',
        'due_date',
        'status',
        'employee_visible',
        'employee_actionable',
        'notes',
        'completed_at',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'employee_visible' => 'boolean',
            'employee_actionable' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }
}
