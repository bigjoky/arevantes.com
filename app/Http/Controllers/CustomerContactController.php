<?php

namespace App\Http\Controllers;

use App\Models\CustomerContact;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerContactController extends Controller
{
    public function store(Request $request, Person $person): RedirectResponse
    {
        $this->ensureCustomer($person);
        $data = $this->validated($request);

        DB::transaction(function () use ($person, $data): void {
            $data['is_primary'] = $data['is_primary'] || ! $person->contacts()->exists();

            if ($data['is_primary']) {
                $person->contacts()->update(['is_primary' => false]);
            }

            $person->contacts()->create($data);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Contact added.')]);
    }

    public function update(Request $request, Person $person, CustomerContact $contact): RedirectResponse
    {
        $this->ensureCustomer($person);
        abort_unless($contact->person_id === $person->id, 404);
        $data = $this->validated($request);

        DB::transaction(function () use ($person, $contact, $data): void {
            if ($contact->is_primary && ! $data['is_primary'] && ! $person->contacts()->whereKeyNot($contact->id)->where('is_primary', true)->exists()) {
                $data['is_primary'] = true;
            }

            if ($data['is_primary']) {
                $person->contacts()->whereKeyNot($contact->id)->update(['is_primary' => false]);
            }

            $contact->update($data);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Contact updated.')]);
    }

    public function destroy(Person $person, CustomerContact $contact): RedirectResponse
    {
        $this->ensureCustomer($person);
        abort_unless($contact->person_id === $person->id, 404);

        DB::transaction(function () use ($person, $contact): void {
            $wasPrimary = $contact->is_primary;
            $contact->delete();

            if ($wasPrimary) {
                $person->contacts()->oldest('id')->first()?->update(['is_primary' => true]);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Contact removed.')]);
    }

    private function validated(Request $request): array
    {
        $roles = array_values(array_unique(array_filter((array) $request->input('roles', []))));
        $isAuthorizedSignatory = $request->boolean('is_authorized_signatory');

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'roles' => ['nullable', 'array', 'max:6'],
            'roles.*' => ['string', 'distinct', Rule::in(['legal_representative', 'billing', 'commercial', 'technical', 'property_manager', 'other'])],
            'document_type' => ['nullable', Rule::in(['passport', 'nif', 'nie', 'other'])],
            'document_number' => [Rule::requiredIf($isAuthorizedSignatory), 'nullable', 'string', 'max:255'],
            'email' => [Rule::requiredIf(in_array('billing', $roles, true)), 'nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'preferred_channel' => ['nullable', Rule::in(['email', 'phone', 'whatsapp', 'sms'])],
            'is_primary' => ['required', 'boolean'],
            'is_authorized_signatory' => [
                'nullable',
                'boolean',
                function (string $attribute, mixed $value, \Closure $fail) use ($roles): void {
                    if (filter_var($value, FILTER_VALIDATE_BOOL) && ! in_array('legal_representative', $roles, true)) {
                        $fail(__('An authorized signatory must have the legal representative role.'));
                    }
                },
            ],
            'notes' => ['nullable', 'string'],
        ]);

        $data['roles'] = $roles ?: ['commercial'];
        $data['is_primary'] = (bool) $data['is_primary'];
        $data['is_authorized_signatory'] = $isAuthorizedSignatory;

        return $data;
    }

    private function ensureCustomer(Person $person): void
    {
        abort_unless($person->category === 'customer', 404);
    }
}
