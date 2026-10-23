<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MunicipalCaseTaxObligation extends Model
{
    protected $fillable = [
        'municipal_case_id', 'tax_type', 'liable_party', 'payment_mode', 'payment_status',
        'assessed_amount', 'notification_date', 'payment_deadline',
        'proof_private_disk', 'proof_storage_path', 'proof_verified_at',
        'proof_verified_by', 'benefit_status', 'benefit_application_deadline',
        'benefit_registry_number', 'source_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'assessed_amount' => 'decimal:2', 'notification_date' => 'date',
            'payment_deadline' => 'date', 'proof_verified_at' => 'datetime',
            'benefit_application_deadline' => 'date', 'source_snapshot' => 'array',
        ];
    }

    public function municipalCase(): BelongsTo
    {
        return $this->belongsTo(MunicipalCase::class);
    }
}
