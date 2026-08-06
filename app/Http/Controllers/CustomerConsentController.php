<?php

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerConsentController extends Controller
{
    public function store(Request $request, Person $person): RedirectResponse
    {
        abort_unless($person->category === 'customer', 404);
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'phone', 'whatsapp', 'sms'])],
            'status' => ['required', Rule::in(['granted', 'denied', 'withdrawn'])],
            'lawful_basis' => ['required', Rule::in(['consent', 'contract', 'legitimate_interest', 'legal_obligation'])],
            'source' => ['nullable', 'string', 'max:255'],
            'captured_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $person->consents()->create([
            ...$data,
            'recorded_by' => $request->user()->id,
            'withdrawn_at' => $data['status'] === 'withdrawn' ? $data['captured_at'] : null,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Consent recorded.')]);
    }
}
