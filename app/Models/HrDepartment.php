<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrDepartment extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    public function employmentProfiles(): HasMany
    {
        return $this->hasMany(HrEmploymentProfile::class, 'department_id');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
