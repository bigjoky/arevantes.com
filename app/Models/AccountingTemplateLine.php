<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingTemplateLine extends Model
{
    protected $fillable = ['account_code', 'side', 'percentage', 'description', 'position'];

    protected function casts(): array
    {
        return ['percentage' => 'decimal:4'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AccountingTemplate::class, 'accounting_template_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_code', 'code');
    }
}
