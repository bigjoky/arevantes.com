<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Read-only lookup of Spanish registered-company data; results require user review. */
final class SupplierRegistryLookup
{
    public function lookup(string $taxId): array
    {
        $key = config('services.supplier_registry.key');
        if (config('services.supplier_registry.provider') !== 'apiempresas' || ! is_string($key) || $key === '') {
            return ['status' => 'unavailable'];
        }
        try {
            $response = Http::acceptJson()->withoutRedirecting()->withHeaders(['X-API-KEY' => $key])
                ->connectTimeout(3)->timeout(6)->get('https://apiempresas.es/api/v1/companies', ['cif' => $taxId]);
        } catch (ConnectionException $e) {
            return ['status' => 'unavailable'];
        }
        if ($response->status() === 404) {
            return ['status' => 'not_found'];
        }
        $data = $response->json('data');
        if (! $response->successful() || ! is_array($data)) {
            return ['status' => 'unavailable'];
        }
        $foundId = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($data['cif'] ?? '')));
        if ($foundId !== $taxId || ! is_string($data['name'] ?? null) || trim($data['name']) === '') {
            return ['status' => 'not_found'];
        }
        $fields = ['legal_name' => $data['name'], 'tax_id' => $foundId, 'country' => 'ES'];
        foreach (['address' => 'address_line_1', 'postal_code' => 'postal_code', 'municipality' => 'city', 'province' => 'province'] as $source => $target) {
            if (is_string($data[$source] ?? null) && trim($data[$source]) !== '') {
                $fields[$target] = $data[$source];
            }
        }

        return ['status' => 'found', 'address_type' => 'domicilio social registral', 'fields' => $fields];
    }
}
