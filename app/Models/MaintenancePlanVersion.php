<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePlanVersion extends Model
{
    protected $fillable = ['maintenance_plan_id', 'version', 'snapshot', 'change_note', 'created_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'snapshot' => 'array'];
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
