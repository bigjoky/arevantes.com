<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplierContactController extends Controller
{
    public function store(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($supplier, $data): void {
            $data['is_primary'] = $data['is_primary'] || ! $supplier->contacts()->exists();
            if ($data['is_primary']) {
                $supplier->contacts()->update(['is_primary' => false]);
            }
            $supplier->contacts()->create($data);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Supplier contact added.')]);
    }

    public function update(Request $request, Supplier $supplier, SupplierContact $contact): RedirectResponse
    {
        abort_unless($contact->supplier_id === $supplier->id, 404);
        $data = $this->validated($request);
        DB::transaction(function () use ($supplier, $contact, $data): void {
            if ($contact->is_primary && ! $data['is_primary'] && ! $supplier->contacts()->whereKeyNot($contact->id)->where('is_primary', true)->exists()) {
                $data['is_primary'] = true;
            }
            if ($data['is_primary']) {
                $supplier->contacts()->whereKeyNot($contact->id)->update(['is_primary' => false]);
            }
            $contact->update($data);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Supplier contact updated.')]);
    }

    public function destroy(Supplier $supplier, SupplierContact $contact): RedirectResponse
    {
        abort_unless($contact->supplier_id === $supplier->id, 404);
        DB::transaction(function () use ($supplier, $contact): void {
            $wasPrimary = $contact->is_primary;
            $contact->delete();
            if ($wasPrimary) {
                $supplier->contacts()->oldest('id')->first()?->update(['is_primary' => true]);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Supplier contact removed.')]);
    }

    private function validated(Request $request): array
    {
        $roles = array_values(array_unique(array_filter((array) $request->input('roles', []))));
        $isSignatory = $request->boolean('is_authorized_signatory');
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'], 'last_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'], 'roles' => ['nullable', 'array', 'max:7'],
            'roles.*' => ['string', 'distinct', Rule::in(['legal_representative', 'billing', 'purchasing', 'technical', 'quality', 'logistics', 'other'])],
            'document_type' => ['nullable', Rule::in(['passport', 'nif', 'nie', 'other'])],
            'document_number' => [Rule::requiredIf($isSignatory), 'nullable', 'string', 'max:255'],
            'email' => [Rule::requiredIf(in_array('billing', $roles, true)), 'nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'], 'preferred_channel' => ['nullable', Rule::in(['email', 'phone', 'whatsapp', 'sms'])],
            'is_primary' => ['required', 'boolean'],
            'is_authorized_signatory' => ['nullable', 'boolean', function (string $attribute, mixed $value, \Closure $fail) use ($roles): void {
                if (filter_var($value, FILTER_VALIDATE_BOOL) && ! in_array('legal_representative', $roles, true)) {
                    $fail(__('An authorized signatory must have the legal representative role.'));
                }
            }],
            'notes' => ['nullable', 'string'],
        ]);
        $data['roles'] = $roles ?: ['purchasing'];
        $data['is_primary'] = (bool) $data['is_primary'];
        $data['is_authorized_signatory'] = $isSignatory;

        return $data;
    }
}
