<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankFinancialProduct extends Model
{
    protected $fillable = [
        'bank_account_id',
        'shared_credit_facility_id',
        'ledger_account_id',
        'type',
        'name',
        'provider',
        'currency',
        'identifier_last_four',
        'contract_reference',
        'credit_limit',
        'used_amount',
        'annual_interest_rate',
        'annual_fee',
        'billing_day',
        'payment_day',
        'starts_on',
        'expires_on',
        'notes',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'used_amount' => 'decimal:2',
            'annual_interest_rate' => 'decimal:4',
            'annual_fee' => 'decimal:2',
            'starts_on' => 'date',
            'expires_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function sharedCreditFacility(): BelongsTo
    {
        return $this->belongsTo(self::class, 'shared_credit_facility_id');
    }

    public function linkedCards(): HasMany
    {
        return $this->hasMany(self::class, 'shared_credit_facility_id');
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
