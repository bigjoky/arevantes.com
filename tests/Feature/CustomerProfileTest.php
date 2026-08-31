<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\CustomerPortalAccess;
use App\Models\Installation;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_customer_profiles_require_fiscal_identity(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('people.store'), [
            'customer_type' => 'business',
            'first_name' => 'Ana',
        ])->assertSessionHasErrors(['company_name', 'tax_id']);

        $this->assertDatabaseCount('people', 0);
    }

    public function test_legal_customer_profile_can_be_created_with_its_primary_contact(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('people.store'), [
            'customer_type' => 'community',
            'first_name' => 'Ana',
            'last_name' => 'López',
            'company_name' => 'Comunidad Jardines del Sur',
            'tax_id' => 'H12345678',
        ])->assertRedirect();

        $this->assertDatabaseHas('people', [
            'category' => 'customer',
            'customer_type' => 'community',
            'company_name' => 'Comunidad Jardines del Sur',
            'tax_id' => 'H12345678',
            'first_name' => 'Ana',
        ]);
    }

    public function test_changing_a_legal_customer_to_residential_removes_the_legal_name(): void
    {
        $user = User::factory()->create();
        $customer = Person::create([
            'category' => 'customer',
            'customer_type' => 'business',
            'first_name' => 'Ana',
            'company_name' => 'Solar Sur SL',
            'tax_id' => 'B12345678',
        ]);

        $this->actingAs($user)->patch(route('people.update', $customer), [
            'customer_type' => 'residential',
            'first_name' => 'Ana',
            'tax_id' => '12345678Z',
        ])->assertRedirect(route('people.show', $customer));

        $this->assertDatabaseHas('people', [
            'id' => $customer->id,
            'customer_type' => 'residential',
            'company_name' => null,
            'tax_id' => '12345678Z',
        ]);
    }

    public function test_customer_profile_combines_commercial_and_operational_history(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana', 'company_name' => 'Solar Customer']);
        $installation = Installation::create(['person_id' => $customer->id, 'project_reference' => 'SOL-360', 'service_type' => 'solar', 'status' => 'active', 'site_address_line_1' => 'Calle Sol 1']);
        Quote::create(['person_id' => $customer->id, 'installation_id' => $installation->id, 'number' => 'PRE-360', 'status' => 'sent', 'issue_date' => today(), 'subtotal' => 200, 'discount_amount' => 0, 'tax_amount' => 42, 'amount' => 242, 'customer_snapshot' => []]);
        SalesDocument::create(['person_id' => $customer->id, 'installation_id' => $installation->id, 'type' => 'invoice', 'number' => 'FAC-360', 'status' => 'partial', 'issue_date' => today(), 'due_date' => today(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 20, 'customer_snapshot' => []]);
        CrmActivity::create(['person_id' => $customer->id, 'title' => 'Call customer', 'status' => 'pending', 'type' => 'task']);
        WorkOrder::create(['installation_id' => $installation->id, 'number' => 'WO-360', 'title' => 'Commissioning', 'status' => 'planned']);

        $this->actingAs($user)->get(route('people.show', $customer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('people/show')
                ->where('customer360.metrics.billed', 121)
                ->where('customer360.metrics.outstanding', 101)
                ->where('customer360.metrics.open_activities', 1)
                ->where('customer360.metrics.active_work_orders', 1)
                ->has('customer360.quotes', 1)
                ->has('customer360.documents', 1)
                ->has('customer360.timeline', 4));
    }

    public function test_new_quote_can_preselect_a_customer(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);

        $this->actingAs($user)->get(route('quotes.create', ['customer' => $customer->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotes/form')
                ->where('defaultCustomerId', $customer->id));
    }

    public function test_customer_list_can_be_searched_filtered_and_paginated(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $match = Person::create([
            'category' => 'customer',
            'customer_type' => 'business',
            'first_name' => 'Ana',
            'company_name' => 'Solar Córdoba SL',
            'lead_source' => 'Referral',
        ]);
        Person::create([
            'category' => 'customer',
            'customer_type' => 'residential',
            'first_name' => 'Luis',
            'lead_source' => 'Website',
        ]);

        $this->actingAs($user)->get(route('people.index', [
            'search' => 'Córdoba',
            'customer_type' => 'business',
            'lead_source' => 'Referral',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.data.0.id', $match->id)
                ->has('people.data', 1)
                ->where('people.total', 1)
                ->where('filters.customer_type', 'business')
                ->has('leadSources', 2));
    }

    public function test_archived_customer_can_be_restored_and_portal_access_is_revoked(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $access = CustomerPortalAccess::create([
            'person_id' => $customer->id,
            'created_by' => $user->id,
            'recipient_email' => 'ana@example.test',
            'token_hash' => hash('sha256', 'portal-token'),
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)->delete(route('people.destroy', $customer))
            ->assertRedirect(route('people.index'));
        $this->assertSoftDeleted('people', ['id' => $customer->id]);
        $this->assertNotNull($access->refresh()->revoked_at);

        $this->get(route('people.index', ['archived' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('people.data', 1)
                ->where('people.data.0.id', $customer->id));

        $this->post(route('people.restore', $customer->id))
            ->assertRedirect(route('people.show', $customer->id));
        $this->assertDatabaseHas('people', ['id' => $customer->id, 'deleted_at' => null]);
    }

    public function test_user_can_log_a_completed_customer_interaction(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);

        $this->actingAs($user)->post(route('activities.store'), [
            'person_id' => $customer->id,
            'type' => 'call',
            'title' => 'Discussed solar proposal',
            'notes' => 'Customer requested a revised panel layout.',
            'priority' => 'normal',
            'status' => 'completed',
        ])->assertRedirect();

        $activity = CrmActivity::where('person_id', $customer->id)->firstOrFail();
        $this->assertSame('call', $activity->type);
        $this->assertSame('completed', $activity->status);
        $this->assertNotNull($activity->completed_at);
    }

    public function test_technician_operations_page_only_receives_their_operational_work(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        Quote::create(['person_id' => $customer->id, 'number' => 'PRE-PRIVATE', 'status' => 'draft', 'issue_date' => today(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'customer_snapshot' => []]);
        CrmActivity::create(['assigned_user_id' => $technician->id, 'title' => 'Technical visit', 'status' => 'pending', 'type' => 'visit']);

        $this->actingAs($technician)->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/index')
                ->where('canManage', false)
                ->has('activities.data', 1)
                ->where('activities.data.0.title', 'Technical visit'));
    }

    public function test_activity_cannot_link_an_installation_from_another_customer(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $other = Person::create(['category' => 'customer', 'first_name' => 'Luis']);
        $installation = Installation::create(['person_id' => $other->id, 'service_type' => 'solar', 'status' => 'active', 'site_address_line_1' => 'Calle Sol 2']);

        $this->actingAs($user)->post(route('activities.store'), [
            'person_id' => $customer->id,
            'installation_id' => $installation->id,
            'type' => 'visit',
            'title' => 'Invalid visit',
            'priority' => 'normal',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('crm_activities', ['title' => 'Invalid visit']);
    }
}
