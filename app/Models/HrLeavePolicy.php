<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrLeavePolicy extends Model
{
    protected $fillable = ['type', 'name', 'annual_allowance_days', 'accrual_method', 'day_count_method', 'carryover_limit_days', 'requires_attachment', 'is_active'];

    public function balances(): HasMany
    {
        return $this->hasMany(HrLeaveBalance::class, 'policy_id');
    }

    protected function casts(): array
    {
        return [
            'annual_allowance_days' => 'decimal:2',
            'carryover_limit_days' => 'decimal:2',
            'requires_attachment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
