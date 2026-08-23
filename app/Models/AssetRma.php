<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRma extends Model
{
    public const ACTIVE_STATUSES = ['requested', 'approved', 'shipped', 'received'];

    protected $fillable = ['installation_asset_id', 'work_order_id', 'service_ticket_id', 'opened_by', 'closed_by', 'number', 'provider', 'external_reference', 'status', 'reason', 'resolution', 'requested_at', 'shipped_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'shipped_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(InstallationAsset::class, 'installation_asset_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function serviceTicket(): BelongsTo
    {
        return $this->belongsTo(ServiceTicket::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
