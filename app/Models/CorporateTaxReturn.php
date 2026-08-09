<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateTaxReturn extends Model
{
    protected $fillable = ['fiscal_year_id', 'regime', 'prior_year_revenue', 'accounting_result', 'permanent_positive', 'permanent_negative', 'temporary_positive', 'temporary_negative', 'loss_carryforward', 'taxable_base', 'gross_tax', 'deductions', 'withholdings', 'instalment_payments', 'net_tax', 'status', 'calculation', 'prepared_by', 'prepared_at', 'filing_reference', 'filed_on', 'filed_by', 'receipt_path', 'receipt_original_name', 'receipt_sha256'];

    protected function casts(): array
    {
        return ['calculation' => 'array', 'prepared_at' => 'datetime', 'filed_on' => 'date'];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }
}
