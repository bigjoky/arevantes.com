<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetEvent extends Model
{
    protected $fillable = ['installation_asset_id', 'work_order_id', 'service_ticket_id', 'recorded_by', 'type', 'summary', 'details', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(InstallationAsset::class, 'installation_asset_id');
    }
}
