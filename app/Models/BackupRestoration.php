<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRestoration extends Model
{
    protected $fillable = [
        'database_backup_id', 'backup_name', 'engine', 'status', 'environment',
        'details', 'rpo_seconds', 'rto_seconds', 'metrics', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['metrics' => 'array', 'verified_at' => 'datetime'];
    }

    public function databaseBackup(): BelongsTo
    {
        return $this->belongsTo(DatabaseBackup::class);
    }
}
