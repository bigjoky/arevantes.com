<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteContactReceipt extends Model
{
    protected $guarded = ['id'];

    public function mailItems(): HasMany
    {
        return $this->hasMany(WebsiteContactMail::class, 'receipt_id');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'processed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
