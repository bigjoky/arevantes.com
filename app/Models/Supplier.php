<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = ['legal_name', 'trade_name', 'tax_id', 'payable_account_code', 'email', 'phone', 'address_line_1', 'address_line_2', 'postal_code', 'city', 'province', 'country', 'payment_terms_days', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function purchaseDocuments(): HasMany
    {
        return $this->hasMany(PurchaseDocument::class);
    }
}
