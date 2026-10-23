<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MunicipalCaseDocument extends Model
{
    protected $fillable = ['municipal_case_id', 'kind', 'status', 'version', 'private_disk', 'storage_path', 'sha256', 'template_snapshot', 'uploaded_by'];

    protected function casts(): array
    {
        return ['template_snapshot' => 'array'];
    }

    public function municipalCase(): BelongsTo
    {
        return $this->belongsTo(MunicipalCase::class);
    }
}
