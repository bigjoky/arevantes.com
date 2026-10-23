<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\InstallationAsset;
use App\Models\MaintenancePlan;
use App\Models\Person;
use App\Models\Quote;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\WorkspaceNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectsRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function project(array $attributes = []): Installation
    {
        static $number = 0;

        return Installation::create([...[
            'project_reference' => 'PRJ-TEST-'.++$number, 'site_name' => 'Instalación solar',
            'service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Calle Sol 1',
            'estimated_budget' => 100,
        ], ...$attributes]);
    }

    private function ticket(Installation $installation, array $attributes = []): ServiceTicket
    {
        return ServiceTicket::create([...['installation_id' => $installation->id, 'number' => 'SAT-'.$installation->id, 'kind' => 'incident', 'priority' => 'normal', 'status' => 'open', 'title' => 'Incidencia real'], ...$attributes]);
    }

    public function test_dashboard_aggregates_the_same_creation_cohort_and_real_power_without_quote_duplicates(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 7)->startOfDay());
        $user = User::factory()->create(['role' => 'commercial']);
        $this->project();
        $solar = $this->project(['status' => 'in_progress', 'system_power_kw' => 40, 'scheduled_date' => '2026-10-06', 'survey_date' => '2026-10-09']);
        $this->project(['status' => 'in_progress', 'system_power_kw' => 15]);
        $this->project(['status' => 'in_progress', 'service_type' => 'ev_charger', 'system_power_kw' => 999]);
        $this->project(['status' => 'completed']);
        $after = $this->project(['status' => 'completed']);
        MaintenancePlan::create(['installation_id' => $after->id, 'name' => 'Revisión real', 'interval_months' => 6, 'starts_on' => '2026-01-01', 'next_due_on' => '2026-10-10', 'is_active' => true]);
        $this->project(['status' => 'cancelled']);
        $this->project(['status' => 'active']);
        $this->project()->forceFill(['created_at' => '2020-01-01'])->save();
        $this->project(['project_reference' => null]);
        Quote::create(['installation_id' => $solar->id, 'number' => 'PRE-A', 'status' => 'accepted', 'service_type' => 'solar', 'amount' => 500]);
        Quote::create(['installation_id' => $solar->id, 'number' => 'PRE-B', 'status' => 'accepted', 'service_type' => 'solar', 'amount' => 900]);
        $this->actingAs($user)->get('/execution')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('execution/dashboard')->where('total', 8)->where('groups.preparation.total', 1)
            ->where('groups.execution.total', 3)->where('groups.completed.total', 1)
            ->where('groups.after_sales.total', 1)->where('groups.cancelled.total', 1)->where('groups.other.total', 1)
            ->where('power', 55)->where('powerDatedCount', 1)->where('value', '1600')
            ->has('powerSeries', 12)->where('powerSeries.11.power', 40)->has('types', 2)->has('upcoming', 2));
        $this->get('/execution/projects?created_from=2026-10-01&created_to=2026-10-31&phase=execution')
            ->assertInertia(fn (Assert $page) => $page->where('pagination.total', 3));
        $this->get('/execution?period=year&year=2020')->assertInertia(fn (Assert $page) => $page->where('total', 1));
    }

    public function test_list_filters_search_sort_pagination_and_deep_links_are_real(): void
    {
        $customer = Person::create(['first_name' => 'Ana', 'company_name' => 'Ágora Solar', 'category' => 'customer']);
        $user = User::factory()->create(['role' => 'commercial']);
        $first = $this->project(['site_name' => 'Almacén Córdoba', 'person_id' => $customer->id, 'scheduled_date' => '2026-10-10']);
        $second = $this->project(['site_name' => 'Nave Málaga', 'person_id' => $customer->id, 'status' => 'in_progress', 'scheduled_date' => '2026-11-10']);
        for ($i = 0; $i < 12; $i++) {
            $this->project();
        }
        $this->actingAs($user)->get('/execution/projects?search=ALMAC%C3%89N&customer='.$customer->id.'&date_from=2026-10-01&date_to=2026-10-31')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('execution/index')->has('items', 1)->where('items.0.id', $first->id)->missing('pagination.data'));
        $this->get('/execution/projects?customer='.$customer->id.'&sort=site_name&direction=desc')
            ->assertInertia(fn (Assert $page) => $page->where('items.0.id', $second->id)->where('pagination.total', 2));
        $this->get('/execution/projects?per_page=10&page=2')->assertInertia(fn (Assert $page) => $page->has('items', 4)->where('pagination.total', 14));
        $this->withExceptionHandling()->get('/execution/projects?sort=estimated_budget')->assertStatus(422);
        $this->get('/execution/projects/'.$first->id.'?tab=summary&search=Almacén')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('execution/show')->where('installation.person_id', $customer->id)->where('tab', 'summary')->where('records', null)->where('costs', null));
        $manual = $this->project(['project_reference' => null]);
        $this->withExceptionHandling()->get('/execution/projects/'.$manual->id)->assertNotFound();
        $this->get('/installations?project='.$first->id)->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.id', $first->id));
    }

    public function test_permissions_hide_money_and_protect_lazy_tabs_and_assignment_scopes(): void
    {
        $tech = User::factory()->create(['role' => 'technician']);
        $other = User::factory()->create(['role' => 'technician']);
        $project = $this->project(['status' => 'completed']);
        $this->ticket($project, ['assigned_user_id' => $other->id]);
        foreach ([$tech->id, $other->id, null] as $index => $assigned) {
            WorkOrder::create(['installation_id' => $project->id, 'number' => 'OT-'.$index, 'title' => 'Trabajo '.$index, 'kind' => 'installation', 'status' => 'scheduled', 'assigned_user_id' => $assigned]);
        }
        $this->actingAs($tech)->get('/execution')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('value', null)->where('permissions.finance', false)->where('groups.completed.total', 1)->missing('groups.after_sales'));
        $this->get('/execution/projects/create')->assertForbidden();
        $this->get('/execution/projects/'.$project->id.'?tab=costs')->assertForbidden();
        $this->get('/execution/projects/'.$project->id.'?tab=quote')->assertForbidden();
        $this->get('/execution/projects/'.$project->id.'?tab=planning')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 1)->where('records.data.0.assigned_user_id', $tech->id));
        $this->get('/installations/'.$project->id.'?tab=manage')->assertOk()->assertInertia(fn (Assert $page) => $page->where('installation.estimated_budget', null));
        $this->get('/after-sales?view=installations')->assertOk()->assertInertia(fn (Assert $page) => $page->where('items.0.open_tickets', 0)->has('tickets', 0));
    }

    public function test_after_sales_requires_completed_installations_and_uses_actual_review_and_incident_dates(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $completed = $this->project(['status' => 'completed', 'completed_date' => '2026-06-01']);
        $active = $this->project(['status' => 'in_progress']);
        $empty = $this->project(['status' => 'completed']);
        MaintenancePlan::create(['installation_id' => $completed->id, 'name' => 'Semestral', 'interval_months' => 6, 'starts_on' => '2026-01-01', 'next_due_on' => today()->subDay(), 'last_completed_on' => '2026-06-01', 'is_active' => true]);
        $this->ticket($completed);
        $this->ticket($active);
        $this->actingAs($user)->get('/after-sales?view=installations')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('after-sales/overview')->where('pagination.total', 2)->where('counts.review', 1)->where('counts.incidents', 1)->has('upcoming', 1)->has('tickets', 1));
        $this->get('/after-sales?view=installations&service_state=review')->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)->where('items.0.id', $completed->id)->where('items.0.last_review', '2026-06-01')->where('items.0.service_state', 'Incidencias'));
        $this->get('/after-sales?view=installations&service_state=none')->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.id', $empty->id));
        $this->get('/after-sales?installation='.$completed->id)->assertInertia(fn (Assert $page) => $page->has('tickets', 1)->where('tickets.0.installation_id', $completed->id));
        $this->get('/maintenance?installation='.$empty->id)->assertInertia(fn (Assert $page) => $page->has('plans', 0));
    }

    public function test_creation_only_lists_unconverted_accepted_quotes_and_summary_is_bounded(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $project = $this->project();
        foreach (['draft', 'accepted', 'sent'] as $status) {
            Quote::create(['number' => 'PRE-'.$status, 'status' => $status, 'service_type' => 'solar', 'amount' => 100]);
        }
        Quote::create(['number' => 'PRE-converted', 'installation_id' => $project->id, 'status' => 'accepted', 'service_type' => 'solar', 'amount' => 100]);
        $this->actingAs($user)->get('/execution/projects/create')->assertInertia(fn (Assert $page) => $page->component('execution/create')->has('quotes.data', 1)->where('quotes.data.0.number', 'PRE-accepted'));
        $this->get('/execution/projects/'.$project->id.'?tab=invalid')->assertStatus(422);
        DB::enableQueryLog();
        $this->get('/execution/projects')->assertOk();
        $before = count(DB::getQueryLog());
        for ($i = 0; $i < 20; $i++) {
            $this->project();
        }
        DB::flushQueryLog();
        $this->get('/execution/projects')->assertOk();
        $after = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($before + 2, $after, 'Project listing must not add queries per row.');
    }

    public function test_parent_navigation_has_exactly_three_children_and_preserves_public_project_pages(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $module = collect(app(WorkspaceNavigation::class)->for($user))->firstWhere('id', 'projects');
        $this->assertSame('/execution', $module['href']);
        $this->assertTrue($module['navigable']);
        $this->assertSame(['projects.projects', 'projects.installations', 'projects.after-sales'], array_column($module['children'], 'id'));
        $this->assertSame('/execution/projects', $module['children'][0]['href']);
        $this->assertSame('/after-sales?view=installations', $module['children'][2]['href']);
        $this->get('/proyectos')->assertOk()->assertInertia(fn (Assert $page) => $page->component('projects/index'));
        $this->get('/execution')->assertRedirect('/login');
    }

    public function test_lazy_details_load_paginated_operational_data_and_existing_cost_service(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $project = $this->project();
        InstallationAsset::create(['installation_id' => $project->id, 'asset_tag' => 'EQ-REAL', 'name' => 'Inversor real', 'category' => 'inverter', 'status' => 'active', 'serial_number' => 'SERIAL-REAL', 'warranty_ends_on' => '2027-10-07']);
        for ($i = 0; $i < 16; $i++) {
            WorkOrder::create(['installation_id' => $project->id, 'number' => 'OT-PAG-'.$i, 'title' => 'Instalación '.$i, 'kind' => 'installation', 'status' => 'scheduled']);
        }
        $this->actingAs($user)->get('/execution/projects/'.$project->id.'?tab=equipment')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 1)->where('records.data.0.serial_number', 'SERIAL-REAL')->where('costs', null));
        $this->get('/execution/projects/'.$project->id.'?tab=planning&page=9&section_page=2')->assertOk()->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.total', 16));
        $this->get('/execution/projects/'.$project->id.'?tab=costs')->assertOk()->assertInertia(fn (Assert $page) => $page->where('records', null)->where('costs.planned_revenue', 100)->where('costs.has_actuals', false));
        foreach (['documents', 'activity', 'installations'] as $tab) {
            $this->get('/execution/projects/'.$project->id.'?tab='.$tab)->assertOk();
        }
        $unknown = $this->project(['estimated_budget' => null]);
        $this->get('/execution')->assertInertia(fn (Assert $page) => $page->where('valueMissingCount', 1));
    }
}
