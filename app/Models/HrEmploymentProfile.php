<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmploymentProfile extends Model
{
    protected $fillable = [
        'person_id',
        'department_id',
        'manager_person_id',
        'employee_number',
        'employment_type',
        'status',
        'work_location',
        'weekly_hours',
        'start_date',
        'probation_end_date',
        'end_date',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'manager_person_id');
    }

    protected function casts(): array
    {
        return [
            'weekly_hours' => 'decimal:2',
            'start_date' => 'date',
            'probation_end_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
