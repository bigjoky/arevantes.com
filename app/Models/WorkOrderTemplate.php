<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrderTemplate extends Model
{
    protected $fillable = ['name', 'kind', 'title', 'estimated_duration_minutes', 'required_skills', 'checklist', 'allowed_transitions', 'is_active'];

    protected function casts(): array
    {
        return ['required_skills' => 'array', 'checklist' => 'array', 'allowed_transitions' => 'array', 'is_active' => 'boolean'];
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
