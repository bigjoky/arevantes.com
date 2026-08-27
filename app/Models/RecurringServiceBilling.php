<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringServiceBilling extends Model
{
    protected $fillable = ['service_cycle_commitment_id', 'sales_document_id', 'status', 'net_amount', 'tax_percent', 'tax_amount', 'gross_amount', 'due_on', 'generation_note', 'prepared_by', 'prepared_at', 'issued_by', 'issued_at'];

    protected function casts(): array
    {
        return [
            'net_amount' => 'decimal:2', 'tax_percent' => 'decimal:2', 'tax_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2', 'due_on' => 'date', 'prepared_at' => 'datetime', 'issued_at' => 'datetime',
        ];
    }

    public function serviceCycleCommitment(): BelongsTo
    {
        return $this->belongsTo(ServiceCycleCommitment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
