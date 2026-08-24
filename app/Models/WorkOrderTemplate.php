<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrderTemplate extends Model
{
    protected $fillable = ['name', 'service_type', 'kind', 'title', 'estimated_duration_minutes', 'required_skills', 'checklist', 'required_evidence', 'handover_documents', 'allowed_transitions', 'is_active', 'is_system'];

    protected function casts(): array
    {
        return ['required_skills' => 'array', 'checklist' => 'array', 'required_evidence' => 'array', 'handover_documents' => 'array', 'allowed_transitions' => 'array', 'is_active' => 'boolean', 'is_system' => 'boolean'];
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
