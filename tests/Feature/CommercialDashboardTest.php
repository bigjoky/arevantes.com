<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\FinancialBudget;
use App\Models\Opportunity;
use App\Models\OpportunityEvent;
use App\Models\OpportunityStageHistory;
use App\Models\Permission;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteStatusHistory;
use App\Models\SalesDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommercialDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 6)->startOfDay());
    }

    public function test_dashboard_has_its_own_route_and_real_aggregates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach ([['invoice', 'issued', 100], ['credit_note', 'issued', -20], ['invoice', 'draft', 999], ['invoice', 'cancelled', 999]] as [$type, $status, $amount]) {
            SalesDocument::create(['number' => 'TEST-SALE-'.str()->random(8), 'type' => $type, 'status' => $status, 'issue_date' => '2026-10-02', 'subtotal' => $amount, 'tax_amount' => 0, 'customer_snapshot' => [], 'amount' => $amount]);
        }
        foreach ([['sent', 300, null], ['sent', 999, '2026-10-01'], ['draft', 999, null], ['accepted', 500, null], ['rejected', 700, null], ['expired', 800, null]] as [$status, $amount, $valid]) {
            Quote::create(['number' => 'TEST-QUOTE-'.str()->random(8), 'status' => $status, 'issue_date' => '2026-10-02', 'amount' => $amount, 'created_by' => $user->id, 'valid_until' => $valid]);
        }
        $this->opportunity($user, 'new');
        $this->opportunity($user, 'won');
        $this->opportunity($user, 'lost');
        $this->get('/commercial')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('commercial/index')->where('overview.kpis.sales', 80)
            ->where('overview.kpis.pending_quotes', 300)->where('overview.kpis.conversion', 50)
            ->where('overview.kpis.pipeline', 1)->has('overview.pipeline', 7)
            ->has('overview.monthly', 12)->where('overview.monthly.11.sales', 80)
            ->where('overview.monthly.11.quotes', 4298)->where('overview.target', null));
        $this->get('/opportunities')->assertOk()->assertInertia(fn (Assert $page) => $page->component('opportunities/index'));
        $this->get('/people')->assertOk();
        $this->get('/quotes')->assertOk();
    }

    public function test_activity_combines_empty_crm_history_with_real_quote_history(): void
    {
        $user = User::factory()->create();
        $quote = Quote::create(['number' => 'ACTIVITY-1', 'status' => 'sent', 'issue_date' => '2026-06-02', 'amount' => 100, 'created_by' => $user->id]);
        QuoteStatusHistory::create(['quote_id' => $quote->id, 'changed_by' => $user->id, 'from_status' => 'draft', 'to_status' => 'sent', 'channel' => 'manual', 'changed_at' => '2026-06-02']);
        $this->actingAs($user)->get('/commercial?period=year')->assertOk()->assertInertia(fn (Assert $page) => $page->has('overview.activity', 1)->where('overview.activity.0.type', 'Presupuesto'));
        $opportunity = $this->opportunity($user, 'new');
        OpportunityEvent::create(['opportunity_id' => $opportunity->id, 'actor_id' => $user->id, 'event_type' => 'created', 'summary' => 'Oportunidad creada', 'occurred_at' => '2026-10-02']);
        $this->get('/commercial?period=year')->assertOk()->assertInertia(fn (Assert $page) => $page->has('overview.activity', 2)->where('overview.activity.0.type', 'CRM'));
    }

    public function test_domain_permissions_are_enforced_in_payload_and_partial_reload(): void
    {
        $user = $this->partialUser(['manage-customers']);
        $this->actingAs($user);
        $this->get('/commercial')->assertInertia(fn (Assert $page) => $page
            ->where('overview.permissions.quotes', false)->where('overview.permissions.sales', false)
            ->where('overview.kpis.sales', null)->where('overview.quotes', null)
            ->where('overview.monthly.0.sales', null)->where('overview.monthly.0.quotes', null));
        $blocked = $this->partialUser([]);
        $this->actingAs($blocked)->get('/commercial')->assertForbidden();
        $this->get('/commercial', ['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/commercial')), 'X-Inertia-Partial-Component' => 'commercial/index', 'X-Inertia-Partial-Data' => 'overview'])->assertForbidden();
    }

    public function test_own_scope_excludes_other_commercials_and_unrelated_customers(): void
    {
        $user = $this->partialUser(['manage-customers', 'manage-quotes']);
        $other = User::factory()->create(['role' => 'commercial']);
        $this->opportunity($user, 'new');
        $this->opportunity($other, 'new');
        Quote::create(['number' => 'TEST-QUOTE-'.str()->random(8), 'status' => 'sent', 'issue_date' => '2026-10-02', 'amount' => 100, 'created_by' => $user->id]);
        Quote::create(['number' => 'TEST-QUOTE-'.str()->random(8), 'status' => 'sent', 'issue_date' => '2026-10-02', 'amount' => 999, 'created_by' => $other->id]);
        $this->actingAs($user)->get('/commercial')->assertInertia(fn (Assert $page) => $page
            ->where('overview.scope', 'own')->where('overview.kpis.pipeline', 1)
            ->where('overview.kpis.pending_quotes', 100)->where('overview.customers.new', 1)
            ->where('overview.kpis.conversion', null));
    }

    public function test_period_filters_are_validated_and_empty_is_distinct_from_unavailable(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/commercial?period=previous_month')->assertInertia(fn (Assert $page) => $page
            ->where('overview.from', '2026-09-01')->where('overview.to', '2026-09-30')
            ->where('overview.kpis.sales', 0)->where('overview.kpis.conversion', null)
            ->has('overview.activity', 0)->has('overview.tasks', 0));
        $this->get('/commercial?period=quarter')->assertInertia(fn (Assert $page) => $page->where('overview.from', '2026-10-01'));
        $this->get('/commercial?period=year')->assertInertia(fn (Assert $page) => $page->where('overview.from', '2026-01-01'));
        $this->get('/commercial?period=invalid')->assertSessionHasErrors('period');
    }

    public function test_targets_reuse_the_real_financial_budget_and_are_not_exposed_to_personal_scope(): void
    {
        $user = User::factory()->create();
        FinancialBudget::create(['year' => 2026, 'month' => 10, 'category' => 'revenue', 'amount' => 3100]);
        $this->actingAs($user)->get('/commercial')->assertInertia(fn (Assert $page) => $page->where('overview.target.amount', 600));
        $this->actingAs($this->partialUser(['manage-installations', 'view-finance']))->get('/commercial')
            ->assertInertia(fn (Assert $page) => $page->where('overview.target', null));
    }

    public function test_summary_query_count_does_not_grow_with_opportunity_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        DB::enableQueryLog();
        $this->get('/commercial')->assertOk();
        $baseline = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 20; $i++) {
            $this->opportunity($user, 'new');
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/commercial')->assertOk();
        $populated = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($baseline + 8, $populated, 'Only fixed eager-load queries may be added for activity rows.');
    }

    private function opportunity(User $owner, string $stage): Opportunity
    {
        $person = Person::create(['category' => 'customer', 'first_name' => 'Test', 'lifecycle_status' => 'active']);
        $opportunity = Opportunity::create(['person_id' => $person->id, 'title' => 'Test opportunity', 'stage' => $stage, 'owner_user_id' => $owner->id, 'created_by' => $owner->id, 'estimated_value' => 100]);
        if (in_array($stage, ['won', 'lost'], true)) {
            OpportunityStageHistory::create(['opportunity_id' => $opportunity->id, 'to_stage' => $stage, 'changed_at' => now(), 'changed_by' => $owner->id]);
        }

        return $opportunity;
    }

    private function partialUser(array $keys): User
    {
        $profile = PermissionProfile::create(['name' => 'Test dashboard', 'slug' => 'test-'.str()->random(8), 'is_system' => false]);
        $profile->permissions()->sync(Permission::whereIn('key', $keys)->pluck('id'));

        return User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $profile->id]);
    }
}
