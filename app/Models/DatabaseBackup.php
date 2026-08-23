<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatabaseBackup extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'engine', 'status', 'disk', 'path', 'bytes', 'checksum', 'manifest',
        'error', 'started_at', 'completed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function restorations(): HasMany
    {
        return $this->hasMany(BackupRestoration::class);
    }
}
