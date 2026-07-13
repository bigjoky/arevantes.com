<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemoteStation extends Model
{
    protected $fillable = [
        'installation_id',
        'provider',
        'remote_identifier',
        'name',
        'address',
        'connection_status',
        'provider_data',
        'discovered_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_data' => 'array',
            'discovered_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }
}
