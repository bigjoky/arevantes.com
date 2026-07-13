<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
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

    public function test_technician_operations_page_does_not_receive_quotes(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        Quote::create(['person_id' => $customer->id, 'number' => 'PRE-PRIVATE', 'status' => 'draft', 'issue_date' => today(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'customer_snapshot' => []]);
        CrmActivity::create(['assigned_user_id' => $technician->id, 'title' => 'Technical visit', 'status' => 'pending', 'type' => 'visit']);

        $this->actingAs($technician)->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/index')
                ->where('canManageQuotes', false)
                ->has('quotes', 0)
                ->has('activities', 1));
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
