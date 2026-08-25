<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkOrder extends Model
{
    protected $fillable = ['installation_id', 'work_order_template_id', 'assigned_user_id', 'created_by', 'number', 'title', 'kind', 'status', 'estimated_duration_minutes', 'required_skills', 'required_evidence', 'handover_documents', 'service_zone', 'allowed_transitions_snapshot', 'scheduled_start', 'scheduled_end', 'instructions', 'completion_notes', 'signed_by_name', 'signature_path', 'signed_at', 'completed_at'];

    protected function casts(): array
    {
        return ['required_skills' => 'array', 'required_evidence' => 'array', 'handover_documents' => 'array', 'allowed_transitions_snapshot' => 'array', 'scheduled_start' => 'datetime', 'scheduled_end' => 'datetime', 'signed_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkOrderTemplate::class, 'work_order_template_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->orderByDesc('started_at');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(WorkOrderChecklistItem::class)->orderBy('position');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WorkOrderAttachment::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    public function stockReservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function purchaseDocuments(): HasMany
    {
        return $this->hasMany(PurchaseDocument::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(WorkOrderVisit::class)->latest('arrived_at');
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(WorkOrderReschedule::class)->latest();
    }

    public function assetEvents(): HasMany
    {
        return $this->hasMany(AssetEvent::class);
    }

    public function serviceTicket(): HasOne
    {
        return $this->hasOne(ServiceTicket::class);
    }
}
