<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SupplierCreation
{
    /** Returns an existing supplier unchanged when its country and normalized tax ID match. */
    public function create(array $data): array
    {
        $identity = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $data['tax_id'] ?? ''));
        $country = strtoupper($data['country']);

        return DB::transaction(function () use ($data, $identity, $country) {
            if ($identity !== '') {
                if (DB::getDriverName() === 'pgsql') {
                    DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['supplier:'.$country.':'.$identity]);
                }
                $existing = Supplier::whereRaw('upper(country) = ?', [$country])->get()->filter(fn ($supplier) => strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $supplier->tax_id ?? '')) === $identity)->values();
                if ($existing->count() > 1) {
                    throw ValidationException::withMessages(['tax_id' => 'Hay varios proveedores con este identificador; revisa el catálogo.']);
                }
                if ($existing->isNotEmpty()) {
                    return ['supplier' => $existing->first(), 'existing' => true];
                }
            }

            return ['supplier' => Supplier::create($data), 'existing' => false];
        });
    }
}
