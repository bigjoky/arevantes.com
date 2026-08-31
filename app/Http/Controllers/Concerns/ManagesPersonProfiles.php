<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait ManagesPersonProfiles
{
    /**
     * Ensure a profile belongs to the section being visited.
     */
    protected function ensurePersonCategory(Person $person, string $category): void
    {
        abort_unless($person->category === $category, 404);
    }

    /**
     * Build the shared page props for the customer and staff sections.
     *
     * @return array<string, string>
     */
    protected function sectionProps(string $category): array
    {
        if ($category === 'staff') {
            return [
                'basePath' => '/staff',
                'title' => 'Staff',
                'description' => 'Team members, roles, contact details, and internal notes.',
                'createTitle' => 'Add staff',
                'createDescription' => 'Create a team profile for installers, sales, admin, or technical staff.',
                'editTitle' => 'Edit staff',
                'editDescription' => 'Update staff contact, role, and internal profile details.',
                'showBackLabel' => 'All staff',
                'submitLabel' => 'Save staff',
            ];
        }

        return [
            'basePath' => '/people',
            'title' => 'People',
            'description' => 'Customer profiles for residential, business, community, and public-sector work.',
            'createTitle' => 'Add customer',
            'createDescription' => 'Create a customer profile for solar, EV charging, clima, or electrical work.',
            'editTitle' => 'Edit customer',
            'editDescription' => 'Update customer contact, billing, address, and service details.',
            'showBackLabel' => 'All customers',
            'submitLabel' => 'Save customer',
        ];
    }

    /**
     * Validate and normalize people form input.
     *
     * @return array<string, mixed>
     */
    protected function validatedPersonData(Request $request, string $category): array
    {
        $requiresLegalIdentity = $category === 'customer'
            && in_array($request->input('customer_type'), ['business', 'community', 'public_sector'], true);

        $validated = $request->validate([
            'customer_type' => ['nullable', Rule::in(['residential', 'business', 'community', 'public_sector'])],
            'lifecycle_status' => ['nullable', Rule::in(['lead', 'active', 'inactive', 'former'])],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'company_name' => [Rule::requiredIf($requiresLegalIdentity), 'nullable', 'string', 'max:255'],
            'tax_id' => [Rule::requiredIf($requiresLegalIdentity), 'nullable', 'string', 'max:255'],
            'staff_role' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', Rule::in(['passport', 'nif', 'nie', 'other'])],
            'document_number' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'locality' => ['nullable', 'string', 'max:255'],
            'province_state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'preferred_language' => ['nullable', 'string', 'max:255'],
            'preferred_contact_channel' => ['nullable', Rule::in(['email', 'phone', 'whatsapp', 'sms'])],
            'do_not_contact' => ['nullable', 'boolean'],
            'service_interests' => ['nullable', 'string'],
            'lead_source' => ['nullable', 'string', 'max:255'],
            'data_source' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'extra_details' => ['nullable', 'string'],
        ]);

        $validated['category'] = $category;
        $validated['lifecycle_status'] = $category === 'customer' ? ($validated['lifecycle_status'] ?? 'active') : null;
        $validated['do_not_contact'] = $category === 'customer' && (bool) ($validated['do_not_contact'] ?? false);
        $validated['service_interests'] = $this->parseLines($validated['service_interests'] ?? null);
        $validated['extra_details'] = $this->parseExtraDetails($validated['extra_details'] ?? null);

        if ($category === 'customer') {
            $validated['staff_role'] = null;

            if (($validated['customer_type'] ?? null) === 'residential') {
                $validated['company_name'] = null;
            }
        }

        if ($category === 'staff') {
            $validated['customer_type'] = null;
            $validated['lead_source'] = null;
            $validated['data_source'] = null;
            $validated['billing_email'] = null;
            $validated['service_interests'] = null;
            $validated['preferred_contact_channel'] = null;
            $validated['do_not_contact'] = false;
        }

        return $validated;
    }

    /**
     * Convert simple "key: value" lines into structured JSON.
     *
     * @return array<int|string, string>|null
     */
    protected function parseExtraDetails(?string $details): ?array
    {
        if (! $details) {
            return null;
        }

        $lines = collect(preg_split('/\R/', $details))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            return null;
        }

        $parsed = [];

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = array_map('trim', explode(':', $line, 2));

                if ($key !== '' && $value !== '') {
                    $parsed[$key] = $value;

                    continue;
                }
            }

            $parsed[] = $line;
        }

        return $parsed;
    }

    /**
     * Convert newline or comma separated text into a compact list.
     *
     * @return list<string>|null
     */
    protected function parseLines(?string $details): ?array
    {
        if (! $details) {
            return null;
        }

        $lines = collect(preg_split('/(?:\R|,)+/', $details))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();

        return $lines ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function personSummary(Person $person): array
    {
        return [
            'id' => $person->id,
            'category' => $person->category,
            'customer_type' => $person->customer_type,
            'lifecycle_status' => $person->lifecycle_status,
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'company_name' => $person->company_name,
            'staff_role' => $person->staff_role,
            'service_interests' => $person->service_interests,
            'document_type' => $person->document_type,
            'document_number' => $person->document_number,
            'phone' => $person->phone,
            'email' => $person->email,
            'city' => $person->city,
            'locality' => $person->locality,
            'country' => $person->country,
            'installations_count' => $person->installations_count ?? null,
            'tags' => $person->relationLoaded('tags') ? $person->tags->map->only(['id', 'name', 'color'])->values() : [],
        ];
    }
}
