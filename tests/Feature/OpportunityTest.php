<?php

namespace Tests\Feature;

use App\Jobs\GeocodeInstallation;
use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\Person;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Geocoding\NominatimGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_user_can_manage_the_opportunity_pipeline(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana', 'company_name' => 'Solar Pipeline SL']);
        $installation = Installation::create(['person_id' => $customer->id, 'service_type' => 'solar', 'status' => 'lead', 'site_address_line_1' => 'Calle Sol 1']);

        $this->actingAs($user)->post(route('opportunities.store'), [
            'person_id' => $customer->id,
            'installation_id' => $installation->id,
            'owner_user_id' => $user->id,
            'title' => 'Photovoltaic system 10 kW',
            'stage' => 'qualified',
            'probability' => 25,
            'estimated_value' => 15000,
            'expected_close_on' => today()->addMonth()->toDateString(),
            'source' => 'referral',
        ])->assertRedirect();

        $opportunity = Opportunity::firstOrFail();
        $this->assertMatchesRegularExpression('/^OPP-\d{4}-\d{5}$/', $opportunity->code);
        $this->assertSame($user->id, $opportunity->created_by);
        $this->assertSame($user->id, $opportunity->owner_user_id);
        $this->assertNotNull($opportunity->expected_close_on);
        $this->assertNotNull($opportunity->nextActivity);
        $this->assertGreaterThan(0, $opportunity->commercial_score);

        $this->get(route('opportunities.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('opportunities/index')
            ->has('opportunities', 1)
            ->where('opportunities.0.stage', 'qualified')
            ->where('metrics.pipeline_value', 15000)
            ->where('metrics.weighted_forecast', 3750));

        $this->patch(route('opportunities.update', $opportunity), [
            'person_id' => $customer->id,
            'installation_id' => $installation->id,
            'owner_user_id' => $user->id,
            'title' => 'Photovoltaic system 10 kW',
            'stage' => 'won',
            'probability' => 100,
            'estimated_value' => 15000,
        ])->assertRedirect();

        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'stage' => 'won', 'probability' => 100]);
        $this->delete(route('opportunities.destroy', $opportunity))->assertRedirect();
        $this->assertSoftDeleted('opportunities', ['id' => $opportunity->id]);
        $this->get(route('opportunities.index', ['archived' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('opportunities', 1)
                ->where('opportunities.0.id', $opportunity->id));
        $this->post(route('opportunities.restore', $opportunity->id))->assertRedirect(route('opportunities.index'));
        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'deleted_at' => null]);
        $this->assertSame(
            [
                ['from_stage' => null, 'to_stage' => 'qualified'],
                ['from_stage' => 'qualified', 'to_stage' => 'won'],
            ],
            OpportunityStageHistory::query()
                ->where('opportunity_id', $opportunity->id)
                ->oldest('id')
                ->get(['from_stage', 'to_stage'])
                ->toArray(),
        );
    }

    public function test_opportunity_rejects_an_installation_from_another_customer(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $other = Person::create(['category' => 'customer', 'first_name' => 'Luis']);
        $installation = Installation::create(['person_id' => $other->id, 'service_type' => 'solar', 'status' => 'lead', 'site_address_line_1' => 'Calle Sol 2']);

        $this->actingAs($user)->post(route('opportunities.store'), [
            'person_id' => $customer->id,
            'installation_id' => $installation->id,
            'title' => 'Invalid link',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 1000,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('opportunities', ['title' => 'Invalid link']);
    }

    public function test_lost_opportunity_requires_a_reason(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);

        $this->actingAs($user)->post(route('opportunities.store'), [
            'person_id' => $customer->id,
            'title' => 'Lost lead',
            'stage' => 'lost',
            'probability' => 0,
            'estimated_value' => 1000,
        ])->assertSessionHasErrors('lost_reason');
    }

    public function test_other_source_and_loss_reason_preserve_their_details(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $payload = [
            'person_id' => $customer->id,
            'title' => 'Custom source lead',
            'stage' => 'lost',
            'probability' => 0,
            'estimated_value' => 1000,
            'source' => 'other',
            'lost_reason' => 'other',
        ];

        $this->actingAs($user)->post(route('opportunities.store'), $payload)
            ->assertSessionHasErrors(['source_detail', 'lost_reason_detail']);

        $this->post(route('opportunities.store'), [
            ...$payload,
            'source_detail' => 'Feria local',
            'lost_reason_detail' => 'Proyecto aplazado por licencia',
        ])->assertRedirect();

        $this->assertDatabaseHas('opportunities', [
            'source' => 'other',
            'source_detail' => 'Feria local',
            'lost_reason' => 'other',
            'lost_reason_detail' => 'Proyecto aplazado por licencia',
        ]);
    }

    public function test_technician_cannot_access_opportunities(): void
    {
        $user = User::factory()->create(['role' => 'technician']);

        $this->actingAs($user)->get(route('opportunities.index'))->assertForbidden();
    }

    public function test_commercial_user_can_schedule_and_filter_next_actions(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $otherUser = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $opportunity = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $user->id,
            'code' => 'OPP-ACTION-1',
            'title' => 'Solar follow-up',
            'stage' => 'qualified',
            'probability' => 25,
            'estimated_value' => 5000,
        ]);
        Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $otherUser->id,
            'code' => 'OPP-OTHER-1',
            'title' => 'Other owner',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 1000,
        ]);

        $this->actingAs($user)->post(route('opportunities.activities.store', $opportunity), [
            'assigned_user_id' => $user->id,
            'type' => 'call',
            'title' => 'Call customer',
            'priority' => 'high',
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $activity = CrmActivity::firstWhere('opportunity_id', $opportunity->id);
        $this->assertNotNull($activity);
        $this->assertSame($customer->id, $activity->person_id);

        $this->get(route('opportunities.index', ['owner' => 'me', 'search' => 'follow-up']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('opportunities', 1)
                ->where('opportunities.0.id', $opportunity->id)
                ->where('opportunities.0.next_activity.title', 'Call customer')
                ->where('filters.owner', 'me'));

        $this->get(route('opportunities.index', ['attention' => 'no_action']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('opportunities', 1)
                ->where('opportunities.0.code', 'OPP-OTHER-1'));
    }

    public function test_pipeline_metrics_use_the_selected_commercial_period(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);

        $this->travelTo(now()->subDays(40));
        $won = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $user->id,
            'code' => 'OPP-METRIC-WON',
            'title' => 'Won project',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 12000,
        ]);
        $lost = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $user->id,
            'code' => 'OPP-METRIC-LOST',
            'title' => 'Lost project',
            'stage' => 'new',
            'probability' => 10,
            'estimated_value' => 4000,
        ]);
        $this->travelBack();

        $won->update(['stage' => 'won', 'probability' => 100]);
        $lost->update(['stage' => 'lost', 'probability' => 0, 'lost_reason' => 'price']);

        $this->actingAs($user)->get(route('opportunities.index', ['period' => '30']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('metrics.won_value', 12000)
                ->where('metrics.new_leads', 0)
                ->where('metrics.won_count', 1)
                ->where('metrics.lost_count', 1)
                ->where('metrics.conversion_rate', 50)
                ->where('metrics.average_cycle_days', 40)
                ->where('filters.period', '30'));
    }

    public function test_opportunity_can_prepare_one_quote_and_sync_its_result(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $product = $this->stockProduct('PANEL-450', '450 W panel', 120);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $installation = Installation::create(['person_id' => $customer->id, 'service_type' => 'solar', 'status' => 'lead', 'site_address_line_1' => 'Calle Sol 3']);
        $opportunity = Opportunity::create([
            'person_id' => $customer->id,
            'installation_id' => $installation->id,
            'owner_user_id' => $user->id,
            'code' => 'OPP-2026-00001',
            'title' => 'Solar installation 8 kW',
            'stage' => 'qualified',
            'probability' => 25,
            'estimated_value' => 12000,
        ]);

        $this->actingAs($user)->get(route('quotes.create', ['opportunity' => $opportunity->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotes/form')
                ->where('defaultCustomerId', $customer->id)
                ->where('opportunityDraft.id', $opportunity->id)
                ->where('opportunityDraft.installation_id', $installation->id)
                ->where('opportunityDraft.estimated_value', 12000));

        $this->post(route('quotes.store'), [
            'opportunity_id' => $opportunity->id,
            'person_id' => $customer->id,
            'installation_id' => $installation->id,
            'issue_date' => now()->toDateString(),
            'lines' => [[
                'product_id' => $product->id,
                'unit_id' => $product->unit_id,
                'tax_rate_id' => $product->tax_rate_id,
                'description' => $opportunity->title,
                'quantity' => 10,
                'unit_price' => 1200,
                'discount_percent' => 0,
                'tax_percent' => 21,
            ]],
        ])->assertRedirect();

        $quote = Quote::firstOrFail();
        $this->assertSame($opportunity->id, $quote->opportunity_id);
        $this->assertSame(1, $quote->versions()->count());
        $this->assertSame('not_required', $quote->approval_status);
        $this->assertSame('10800.00', $quote->expected_margin_amount);
        $this->assertSame('proposal', $opportunity->refresh()->stage);
        $this->assertDatabaseHas('crm_activities', [
            'person_id' => $customer->id,
            'installation_id' => $installation->id,
            'assigned_user_id' => $user->id,
            'type' => 'reminder',
            'status' => 'pending',
        ]);

        $this->get(route('quotes.create', ['opportunity' => $opportunity->id]))->assertStatus(422);
        $this->delete(route('opportunities.destroy', $opportunity))->assertStatus(422);
        $this->patch(route('quotes.status.update', $quote), ['status' => 'accepted'])->assertRedirect();
        $this->assertSame('won', $opportunity->refresh()->stage);
        $this->assertSame(100, $opportunity->probability);
        $this->assertSame('approved', $installation->refresh()->status);
        $this->assertDatabaseHas('installation_material_requirements', [
            'installation_id' => $installation->id,
            'quote_id' => $quote->id,
            'product_id' => $product->id,
            'planned_quantity' => 10,
            'planned_unit_cost' => 120,
        ]);
    }

    public function test_accepted_quote_can_create_the_installation_survey_and_work_order(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        $product = $this->stockProduct('INV-5K', '5 kW inverter', 950);
        $customer = Person::create([
            'category' => 'customer',
            'first_name' => 'Ana',
            'address_line_1' => 'Calle del Sol 15',
            'city' => 'Cordoba',
            'province_state' => 'Cordoba',
            'postal_code' => '14001',
            'country' => 'Spain',
        ]);
        $opportunity = Opportunity::create([
            'person_id' => $customer->id,
            'owner_user_id' => $user->id,
            'code' => 'OPP-2026-00002',
            'title' => 'Solar installation 5 kW',
            'stage' => 'won',
            'probability' => 100,
            'estimated_value' => 9000,
        ]);
        $quote = Quote::create([
            'opportunity_id' => $opportunity->id,
            'person_id' => $customer->id,
            'number' => 'PRE-2026-00002',
            'status' => 'accepted',
            'issue_date' => today(),
            'subtotal' => 9000,
            'discount_amount' => 0,
            'tax_amount' => 1890,
            'amount' => 10890,
            'customer_snapshot' => [],
        ]);
        $quote->lines()->create([
            'product_id' => $product->id,
            'unit_id' => $product->unit_id,
            'tax_rate_id' => $product->tax_rate_id,
            'position' => 1,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => 1400,
            'discount_percent' => 0,
            'tax_percent' => 21,
            'subtotal' => 1400,
            'discount_amount' => 0,
            'tax_amount' => 294,
            'total' => 1694,
        ]);
        $scheduledAt = now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s');

        $response = $this->actingAs($user)->post(route('quotes.project.store', $quote), [
            'service_type' => 'solar',
            'technician_user_id' => $technician->id,
            'scheduled_at' => $scheduledAt,
            'site_name' => 'Ana solar project',
            'site_address_line_1' => 'Calle del Sol',
            'site_street_number' => '15',
            'site_city' => 'Cordoba',
            'site_province_state' => 'Cordoba',
            'site_postal_code' => '14001',
            'site_country' => 'Spain',
        ]);

        $installation = Installation::latest('id')->firstOrFail();
        $response->assertRedirect(route('installations.show', $installation));
        $this->assertSame('survey_booked', $installation->status);
        $this->assertSame('solar', $installation->service_type);
        $this->assertSame('15', $installation->site_street_number);
        $this->assertSame('10890.00', $installation->estimated_budget);
        $this->assertSame($installation->id, $quote->refresh()->installation_id);
        $this->assertSame($installation->id, $opportunity->refresh()->installation_id);
        $this->assertDatabaseHas('installation_material_requirements', [
            'installation_id' => $installation->id,
            'quote_id' => $quote->id,
            'product_id' => $product->id,
            'planned_quantity' => 1,
            'planned_unit_cost' => 950,
        ]);

        $order = WorkOrder::where('installation_id', $installation->id)->firstOrFail();
        $this->assertSame($technician->id, $order->assigned_user_id);
        $this->assertSame('planned', $order->status);
        $this->assertGreaterThan(0, $order->checklistItems()->count());
        $this->assertDatabaseHas('crm_activities', [
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'type' => 'visit',
            'priority' => 'high',
        ]);
        Queue::assertPushed(GeocodeInstallation::class, fn (GeocodeInstallation $job) => $job->installationId === $installation->id);

        $this->post(route('quotes.project.store', $quote), [])->assertStatus(422);
        $this->assertSame(1, Installation::where('person_id', $customer->id)->count());
    }

    private function stockProduct(string $sku, string $name, float $cost): Product
    {
        return Product::create([
            'type' => 'product',
            'sku' => $sku,
            'name' => $name,
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => $cost * 1.5,
            'cost_price' => $cost,
            'track_stock' => true,
            'track_serials' => false,
            'is_active' => true,
        ]);
    }

    public function test_project_geocoding_prefers_an_exact_house_number_match(): void
    {
        $installation = Installation::create([
            'service_type' => 'solar',
            'status' => 'survey_booked',
            'site_address_line_1' => 'Calle del Sol',
            'site_street_number' => '15',
            'site_city' => 'Cordoba',
            'site_country' => 'Spain',
        ]);
        $geocoder = $this->mock(NominatimGeocoder::class);
        $geocoder->shouldReceive('search')->once()->andReturn([
            ['provider' => 'nominatim', 'place_id' => 'wrong', 'osm_type' => 'node', 'osm_id' => '1', 'latitude' => '37.8800000', 'longitude' => '-4.7800000', 'display_name' => 'Calle del Sol', 'house_number_match' => false],
            ['provider' => 'nominatim', 'place_id' => 'exact', 'osm_type' => 'node', 'osm_id' => '2', 'latitude' => '37.8812345', 'longitude' => '-4.7712345', 'display_name' => 'Calle del Sol 15', 'house_number_match' => true],
        ]);

        (new GeocodeInstallation($installation->id))->handle($geocoder);

        $installation->refresh();
        $this->assertSame('37.8812345', $installation->site_latitude);
        $this->assertSame('-4.7712345', $installation->site_longitude);
        $this->assertSame('exact', $installation->site_geocoding_place_id);
        $this->assertNotNull($installation->site_geocoded_at);
    }
}
