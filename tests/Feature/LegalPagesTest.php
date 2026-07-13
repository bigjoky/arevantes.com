<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_pages_are_public_and_use_company_profile_data(): void
    {
        CompanyProfile::query()->firstOrFail()->update([
            'legal_name' => 'AREVANTES, S.L.',
            'trade_name' => 'Arevantes',
            'tax_id' => 'B19795608',
            'email' => 'contact@arevantes.com',
        ]);

        foreach ([
            'legal.notice' => 'notice',
            'legal.privacy' => 'privacy',
            'legal.cookies' => 'cookies',
        ] as $route => $document) {
            $this->get(route($route))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('legal/show')
                    ->where('document', $document)
                    ->where('company.legal_name', 'AREVANTES, S.L.')
                    ->where('company.tax_id', 'B19795608')
                    ->where('company.email', 'contact@arevantes.com'));
        }
    }
}
