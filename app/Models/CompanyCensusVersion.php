<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyCensusVersion extends Model
{
    protected $fillable = ['company_profile_id', 'year', 'status', 'settings', 'company_snapshot', 'company_fingerprint', 'created_by', 'author_name', 'source_reference', 'reason'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'settings' => 'array', 'company_snapshot' => 'array'];
    }
}
