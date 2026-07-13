<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAttachment extends Model
{
    protected $fillable = ['uploaded_by', 'original_name', 'path', 'mime_type', 'size'];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
