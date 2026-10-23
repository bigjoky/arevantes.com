<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CompanyDocument extends Model
{
    protected $fillable = ['company_profile_id', 'created_by', 'title', 'description', 'category', 'visibility', 'status', 'expires_at', 'version', 'revision', 'upload_token', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['expires_at' => 'date', 'version' => 'integer', 'revision' => 'integer', 'created_by' => 'integer', 'company_profile_id' => 'integer'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CompanyDocumentVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(CompanyDocumentVersion::class)->ofMany('version', 'max');
    }
}
