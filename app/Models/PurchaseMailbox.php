<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseMailbox extends Model
{
    protected $fillable = [
        'name', 'email', 'host', 'port', 'encryption', 'validate_certificate', 'username', 'password',
        'folder', 'lookback_days', 'auto_extract', 'is_active', 'last_sync_attempt_at', 'last_synced_at', 'last_error',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'validate_certificate' => 'boolean',
            'auto_extract' => 'boolean',
            'is_active' => 'boolean',
            'last_sync_attempt_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PurchaseMailboxMessage::class);
    }
}
