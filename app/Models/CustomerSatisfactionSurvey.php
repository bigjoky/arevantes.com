<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSatisfactionSurvey extends Model
{
    protected $fillable = ['person_id', 'customer_portal_access_id', 'service_ticket_id', 'installation_id', 'context', 'score', 'comments', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }
}
