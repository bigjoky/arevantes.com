<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCorrectivePlanEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['service_corrective_plan_id', 'event', 'from_status', 'to_status', 'note', 'changes', 'actor_id', 'created_at'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ServiceCorrectivePlan::class, 'service_corrective_plan_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
