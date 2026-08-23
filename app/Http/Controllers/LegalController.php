<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function notice(): Response
    {
        return $this->render('notice');
    }

    public function privacy(): Response
    {
        return $this->render('privacy');
    }

    public function cookies(): Response
    {
        return $this->render('cookies');
    }

    private function render(string $document): Response
    {
        $company = CompanyProfile::query()->first();

        return Inertia::render('legal/show', [
            'document' => $document,
            'siteUrl' => rtrim(config('app.url'), '/'),
            'company' => [
                'legal_name' => $company?->legal_name ?? config('company.legal_name'),
                'trade_name' => $company?->trade_name ?? 'Arevantes',
                'tax_id' => $company?->tax_id ?? config('company.tax_id'),
                'address' => $company?->address ?? config('company.address'),
                'postal_code' => $company?->postal_code ?? config('company.postal_code'),
                'city' => $company?->city ?? config('company.city'),
                'province' => $company?->province ?? config('company.province'),
                'country' => $company?->country ?? config('company.country'),
                'email' => $company?->email ?? config('company.email'),
                'phone' => $company?->phone ?? config('company.phone'),
                'website' => $company?->website,
                'commercial_registry' => $company?->commercial_registry,
            ],
        ]);
    }
}
