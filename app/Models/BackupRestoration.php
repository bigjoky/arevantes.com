<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupRestoration extends Model
{
    protected $fillable = ['backup_name', 'status', 'environment', 'details', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }
}
