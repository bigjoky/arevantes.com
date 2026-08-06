<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerDocumentAcceptance extends Model
{
    protected $fillable = ['person_id', 'customer_portal_access_id', 'document_type', 'document_id', 'document_version', 'document_hash', 'accepted_at', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }
}
