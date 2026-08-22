<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrEmploymentDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'person_id',
        'uploaded_by',
        'category',
        'title',
        'original_name',
        'path',
        'mime_type',
        'size',
        'visibility',
        'expires_at',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function casts(): array
    {
        return ['expires_at' => 'date'];
    }
}
