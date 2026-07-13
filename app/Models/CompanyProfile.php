<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $fillable = ['legal_name', 'trade_name', 'tax_id', 'address', 'postal_code', 'city', 'province', 'country', 'email', 'phone', 'website', 'iban', 'bic', 'commercial_registry', 'invoice_footer', 'logo_path'];
}
