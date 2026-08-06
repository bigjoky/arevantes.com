<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeaveAttachment extends Model
{
    protected $fillable = ['leave_request_id', 'uploaded_by', 'original_name', 'path', 'mime_type', 'size'];

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(HrLeaveRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
