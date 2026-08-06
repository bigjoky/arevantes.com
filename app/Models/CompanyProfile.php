<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $fillable = ['legal_name', 'trade_name', 'tax_id', 'address', 'postal_code', 'city', 'province', 'country', 'email', 'phone', 'website', 'iban', 'bic', 'commercial_registry', 'invoice_footer', 'logo_path', 'operating_variance_alert_percent', 'cash_variance_alert_percent', 'liquidity_buffer_amount'];

    protected function casts(): array
    {
        return [
            'operating_variance_alert_percent' => 'decimal:2',
            'cash_variance_alert_percent' => 'decimal:2',
            'liquidity_buffer_amount' => 'decimal:2',
        ];
    }
}
