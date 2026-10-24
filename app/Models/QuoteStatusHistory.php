<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteStatusHistory extends Model
{
    protected $fillable = ['quote_id', 'changed_by', 'from_status', 'to_status', 'channel', 'reason', 'changed_at', 'signed_by_name', 'proof_path', 'proof_name', 'document_version'];

    protected $hidden = ['proof_path'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
