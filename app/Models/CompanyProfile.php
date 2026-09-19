<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    public function documentSnapshot(): array
    {
        return $this->only(['legal_name', 'trade_name', 'tax_id', 'address', 'postal_code', 'city', 'province', 'country', 'email', 'phone', 'website', 'iban', 'bic', 'commercial_registry', 'invoice_footer']);
    }

    protected $fillable = ['legal_name', 'trade_name', 'tax_id', 'address', 'postal_code', 'city', 'province', 'country', 'email', 'phone', 'website', 'iban', 'bic', 'commercial_registry', 'invoice_footer', 'logo_path', 'operating_variance_alert_percent', 'cash_variance_alert_percent', 'liquidity_buffer_amount', 'customer_concentration_alert_percent'];

    protected function casts(): array
    {
        return [
            'operating_variance_alert_percent' => 'decimal:2',
            'cash_variance_alert_percent' => 'decimal:2',
            'liquidity_buffer_amount' => 'decimal:2',
            'customer_concentration_alert_percent' => 'decimal:2',
        ];
    }
}
