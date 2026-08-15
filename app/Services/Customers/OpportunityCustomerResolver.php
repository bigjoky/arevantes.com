<?php

namespace App\Services\Customers;

use App\Models\Person;
use App\Support\PhoneNormalizer;
use Illuminate\Validation\ValidationException;

final class OpportunityCustomerResolver
{
    public function findByPhone(string $phone): ?Person
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        return Person::query()
            ->where('category', 'customer')
            ->where('phone_normalized', $normalized)
            ->oldest('id')
            ->first();
    }

    /**
     * @param  array{first_name?: ?string, last_name?: ?string, company_name?: ?string, email?: ?string}  $attributes
     */
    public function resolve(string $phone, array $attributes, ?int $preferredPersonId = null): Person
    {
        $normalized = PhoneNormalizer::normalize($phone);
        $matched = $this->findByPhone($phone);

        if ($matched) {
            return $matched;
        }

        if ($preferredPersonId) {
            $preferred = Person::query()
                ->where('category', 'customer')
                ->findOrFail($preferredPersonId);

            if ($preferred->phone_normalized && $preferred->phone_normalized !== $normalized) {
                throw ValidationException::withMessages([
                    'customer_phone' => __('The mobile number does not match the selected customer.'),
                ]);
            }

            if (! $preferred->phone_normalized) {
                $preferred->update(['phone' => trim($phone)]);
            }

            return $preferred;
        }

        if (blank($attributes['first_name'] ?? null) && blank($attributes['company_name'] ?? null)) {
            throw ValidationException::withMessages([
                'customer_first_name' => __('Enter a customer name or company name to create the customer.'),
            ]);
        }

        return Person::create([
            'category' => 'customer',
            'customer_type' => filled($attributes['company_name'] ?? null) ? 'business' : 'residential',
            'lifecycle_status' => 'lead',
            'first_name' => filled($attributes['first_name'] ?? null)
                ? trim((string) $attributes['first_name'])
                : trim((string) $attributes['company_name']),
            'last_name' => filled($attributes['last_name'] ?? null) ? trim((string) $attributes['last_name']) : null,
            'company_name' => filled($attributes['company_name'] ?? null) ? trim((string) $attributes['company_name']) : null,
            'phone' => trim($phone),
            'email' => filled($attributes['email'] ?? null) ? mb_strtolower(trim((string) $attributes['email'])) : null,
            'country' => 'Spain',
            'preferred_language' => 'es',
            'preferred_contact_channel' => 'phone',
            'lead_source' => 'CRM',
            'data_source' => 'opportunity_form',
        ]);
    }
}
