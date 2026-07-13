<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionReminder extends Model
{
    protected $fillable = ['user_id', 'category', 'reminder_on', 'item_count'];

    protected function casts(): array
    {
        return ['reminder_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
