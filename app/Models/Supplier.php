<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['legal_name', 'trade_name', 'tax_id', 'payable_account_code', 'email', 'phone', 'address_line_1', 'address_line_2', 'postal_code', 'city', 'province', 'country', 'iban', 'bic', 'payment_terms_days', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
