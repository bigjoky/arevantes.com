<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InstallationPersonController extends Controller
{
    public function store(Request $request, Installation $installation): RedirectResponse
    {
        $data = $request->validate([
            'person_id' => ['required', Rule::exists('people', 'id')->where('category', 'customer')],
            'role' => ['required', Rule::in(['owner', 'co_owner', 'tenant', 'authorized_contact'])],
            'ownership_percentage' => ['nullable', 'numeric', 'between:0.01,100'],
        ]);

        $installation->people()->syncWithoutDetaching([
            $data['person_id'] => [
                'role' => $data['role'],
                'ownership_percentage' => $data['ownership_percentage'] ?? null,
                'is_primary' => (int) $installation->person_id === (int) $data['person_id'],
            ],
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Person assigned to the installation.')]);
    }

    public function destroy(Installation $installation, Person $person): RedirectResponse
    {
        abort_if((int) $installation->person_id === (int) $person->id, 422, __('The primary owner cannot be removed. Select another primary customer first.'));
        $installation->people()->detach($person->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Person removed from the installation.')]);
    }
}
