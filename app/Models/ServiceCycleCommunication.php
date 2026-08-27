<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCycleCommunication extends Model
{
    protected $fillable = ['service_cycle_commitment_id', 'person_id', 'type', 'revision', 'supersedes_communication_id', 'channel', 'status', 'subject', 'message', 'scheduled_for', 'created_by', 'confirmed_by', 'confirmed_at', 'sent_by', 'sent_at', 'delivery_note', 'superseded_at'];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'scheduled_for' => 'datetime', 'confirmed_at' => 'datetime', 'sent_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    public function serviceCycleCommitment(): BelongsTo
    {
        return $this->belongsTo(ServiceCycleCommitment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
