<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\SalesDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_user_can_search_customers_installations_and_sales(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'company_name' => 'Buscador Solar SL', 'first_name' => 'Ana']);
        Installation::create(['person_id' => $customer->id, 'project_reference' => 'BUSCADOR-77', 'site_name' => 'Buscador Solar', 'site_address_line_1' => 'Calle Luz', 'site_city' => 'Córdoba', 'site_country' => 'Spain', 'service_type' => 'solar_pv', 'status' => 'active']);
        SalesDocument::create(['type' => 'invoice', 'person_id' => $customer->id, 'number' => 'FAC-BUSCADOR-77', 'status' => 'issued', 'issue_date' => now(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0, 'customer_snapshot' => []]);
        Opportunity::create(['person_id' => $customer->id, 'code' => 'OPP-BUSCADOR-77', 'title' => 'Buscador opportunity', 'stage' => 'new', 'probability' => 10, 'estimated_value' => 5000]);

        $response = $this->actingAs($user)->getJson(route('global-search', ['q' => 'BUSCADOR']));
        $response->assertOk()->assertJsonCount(4, 'results');
        $this->assertEqualsCanonicalizing(['Customers', 'Opportunities', 'Installations', 'Sales'], collect($response->json('results'))->pluck('group')->all());
    }

    public function test_technician_search_does_not_expose_customer_or_financial_results(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $customer = Person::create(['category' => 'customer', 'company_name' => 'Privado Solar SL', 'first_name' => 'Ana']);
        Installation::create(['person_id' => $customer->id, 'project_reference' => 'PRIVADO-88', 'site_name' => 'Privado Solar', 'site_address_line_1' => 'Calle Luz', 'site_city' => 'Córdoba', 'site_country' => 'Spain', 'service_type' => 'solar_pv', 'status' => 'active']);
        SalesDocument::create(['type' => 'invoice', 'person_id' => $customer->id, 'number' => 'FAC-PRIVADO-88', 'status' => 'issued', 'issue_date' => now(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0, 'customer_snapshot' => []]);

        $results = $this->actingAs($user)->getJson(route('global-search', ['q' => 'PRIVADO']))->assertOk()->json('results');
        $this->assertSame(['Installations'], collect($results)->pluck('group')->unique()->values()->all());
    }
}
