<?php

namespace App\Http\Controllers;

use App\Services\SupplierCreation;
use App\Services\SupplierRegistryLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class QuickSupplierController extends Controller
{
    public function lookup(Request $request, SupplierRegistryLookup $registry): JsonResponse
    {
        $data = $request->validate(['tax_id' => ['required', 'string', 'max:30'], 'country' => ['required', 'in:ES']]);
        $taxId = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $data['tax_id']));
        if (! preg_match('/^[A-Z][0-9]{7}[A-Z0-9]$/', $taxId)) {
            throw ValidationException::withMessages(['tax_id' => 'La consulta admite CIF de sociedades españolas. Introduce los demás proveedores manualmente.']);
        }

        return response()->json($registry->lookup($taxId));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tax_id' => ['required', 'string', 'max:30'], 'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'], 'country' => ['required', 'string', 'size:2', 'regex:/^[a-zA-Z]{2}$/'],
            'address_line_1' => ['nullable', 'string', 'max:255'], 'address_line_2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'], 'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
        ]);
        $data['tax_id'] = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $data['tax_id']));
        $data['country'] = strtoupper($data['country']);
        if ($data['tax_id'] === '') {
            throw ValidationException::withMessages(['tax_id' => 'Introduce un identificador fiscal válido.']);
        }
        $result = app(SupplierCreation::class)->create([...$data, 'is_active' => true, 'payment_terms_days' => 0]);

        return response()->json(['existing' => $result['existing'], 'supplier' => $result['supplier']->only(['id', 'legal_name', 'trade_name', 'tax_id', 'country', 'is_active'])], $result['existing'] ? 200 : 201);
    }
}
