<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCorrectiveProposal extends Model
{
    protected $fillable = ['service_corrective_plan_id', 'type', 'title', 'rationale', 'payload', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ServiceCorrectivePlan::class, 'service_corrective_plan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
