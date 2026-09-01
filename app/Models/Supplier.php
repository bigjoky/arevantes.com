<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = ['legal_name', 'trade_name', 'tax_id', 'payable_account_code', 'email', 'phone', 'address_line_1', 'address_line_2', 'postal_code', 'city', 'locality', 'province', 'country', 'iban', 'bic', 'payment_terms_days', 'notes', 'is_active'];

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

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class)->orderByDesc('is_primary')->orderBy('first_name');
    }

    /** @return array<string, mixed> */
    public function purchaseSnapshot(): array
    {
        $billingContact = $this->contacts()->whereJsonContains('roles', 'billing')->orderByDesc('is_primary')->first();
        $purchasingContact = $this->contacts()->whereJsonContains('roles', 'purchasing')->orderByDesc('is_primary')->first();
        $representative = $this->contacts()->where('is_authorized_signatory', true)->orderByDesc('is_primary')->first();

        return [
            'legal_name' => $this->legal_name, 'trade_name' => $this->trade_name, 'tax_id' => $this->tax_id,
            'email' => $this->email, 'phone' => $this->phone, 'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2, 'postal_code' => $this->postal_code, 'city' => $this->city, 'locality' => $this->locality,
            'province' => $this->province, 'country' => $this->country, 'iban' => $this->iban, 'bic' => $this->bic,
            'payment_terms_days' => $this->payment_terms_days,
            'billing_contact' => $this->contactSnapshot($billingContact),
            'purchasing_contact' => $this->contactSnapshot($purchasingContact),
            'authorized_signatory' => $this->contactSnapshot($representative, true),
        ];
    }

    /** @return array<string, mixed>|null */
    private function contactSnapshot(?SupplierContact $contact, bool $withDocument = false): ?array
    {
        if (! $contact) {
            return null;
        }

        return array_filter([
            'name' => trim($contact->first_name.' '.($contact->last_name ?? '')), 'position' => $contact->position,
            'email' => $contact->email, 'phone' => $contact->phone,
            'document_type' => $withDocument ? $contact->document_type : null,
            'document_number' => $withDocument ? $contact->document_number : null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
