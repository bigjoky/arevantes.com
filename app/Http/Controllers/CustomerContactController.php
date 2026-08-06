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
        $contact->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Contact removed.')]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'preferred_channel' => ['nullable', Rule::in(['email', 'phone', 'whatsapp', 'sms'])],
            'is_primary' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function ensureCustomer(Person $person): void
    {
        abort_unless($person->category === 'customer', 404);
    }
}
