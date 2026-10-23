<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\EngineeringDossier;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\OpportunityEvent;
use App\Models\Permission;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Customers\CustomerWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomersRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function customer(array $attributes = []): Person
    {
        return Person::create(['category' => 'customer', 'first_name' => 'Ana', 'last_name' => 'García', 'customer_type' => 'business', 'company_name' => 'Solar Norte', 'lifecycle_status' => 'active', 'province_state' => 'Córdoba', ...$attributes]);
    }

    private function restrictedUser(): User
    {
        $profile = PermissionProfile::create(['name' => 'Clientes únicamente', 'slug' => 'customer-only-test']);
        $permission = Permission::firstOrCreate(['key' => 'manage-customers'], ['name' => 'Clientes', 'category' => 'commercial']);
        $profile->permissions()->attach($permission);

        return User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $profile->id]);
    }

    private function invoice(Person $person, string $number, string $status, int $amount): SalesDocument
    {
        return SalesDocument::create(['person_id' => $person->id, 'number' => $number, 'type' => 'invoice', 'status' => $status, 'issue_date' => today(), 'subtotal' => $amount, 'discount_amount' => 0, 'tax_amount' => 0, 'amount' => $amount, 'paid_amount' => 0, 'customer_snapshot' => []]);
    }

    public function test_list_search_filter_sort_and_pagination_are_real_queries(): void
    {
        $user = User::factory()->create();
        $match = $this->customer(['tax_id' => 'B12345678', 'email' => 'ana@example.test', 'phone' => '600123456']);
        $this->customer(['company_name' => 'Otro', 'customer_type' => 'residential', 'province_state' => 'Málaga']);
        foreach (['solar', 'B12345678', 'ana@example.test', '600123456', 'Ana García'] as $search) {
            $this->actingAs($user)->get('/people?'.http_build_query(['search' => $search, 'customer_type' => 'business', 'province_state' => 'Córdoba']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('people.data', 1)->where('people.data.0.id', $match->id));
        }
        foreach (range(1, 18) as $i) {
            $this->customer(['company_name' => sprintf('Cliente %02d', $i)]);
        }
        $this->get('/people?sort=name&direction=asc&per_page=15&page=2')->assertOk()->assertInertia(fn (Assert $p) => $p->where('people.total', 20)->where('people.from', 16)->has('people.data', 5)->where('filters.sort', 'name'));
    }

    public function test_volume_excludes_drafts_cancelled_and_other_customers(): void
    {
        $person = $this->customer();
        $other = $this->customer();
        $this->invoice($person, 'FAC-A', 'issued', 121);
        $this->invoice($person, 'FAC-B', 'paid', 242);
        $this->invoice($person, 'FAC-D', 'draft', 999);
        $this->invoice($person, 'FAC-C', 'cancelled', 999);
        $this->invoice($other, 'FAC-O', 'issued', 5000);
        $this->actingAs(User::factory()->create())->get('/people?search=Solar&sort=volume&direction=desc')->assertOk()->assertInertia(fn (Assert $p) => $p->where('people.data.1.id', $person->id)->where('people.data.1.volume', 363));
        $this->get('/people/'.$person->id)->assertOk()->assertInertia(fn (Assert $p) => $p->where('customerWorkspace.financial.billed', 363)->where('customerWorkspace.financial.outstanding', 121)->where('customerWorkspace.domains.invoices.total', 4)->has('customerWorkspace.domains.invoices.data', 3));
    }

    public function test_restricted_customer_user_receives_no_other_domain_data(): void
    {
        $person = $this->customer();
        $this->invoice($person, 'PRIVATE-FAC', 'issued', 12345);
        Quote::create(['person_id' => $person->id, 'number' => 'PRIVATE-PRE', 'status' => 'draft', 'issue_date' => today(), 'amount' => 500, 'customer_snapshot' => []]);
        $this->actingAs($this->restrictedUser())->get('/people')->assertOk()->assertInertia(fn (Assert $p) => $p->where('people.data.0.volume', null)->where('people.data.0.quotes_count', null));
        $this->get('/people/'.$person->id)->assertOk()->assertInertia(fn (Assert $p) => $p->where('customerWorkspace.financial', null)->where('customerWorkspace.domains.invoices', null)->where('customerWorkspace.domains.quotes', null)->where('customerWorkspace.domains.documents', null)->where('customerWorkspace.domains.activity.total', 0)->missing('customer360'));
        foreach (['invoices', 'quotes', 'projects', 'installations', 'documents'] as $tab) {
            $this->get('/people/'.$person->id.'?tab='.$tab)->assertForbidden();
        }
    }

    public function test_detail_deep_link_lazy_tabs_and_customer_scoping(): void
    {
        $person = $this->customer();
        $other = $this->customer();
        foreach (range(1, 18) as $i) {
            Opportunity::create(['person_id' => $person->id, 'title' => 'Oportunidad '.$i, 'code' => 'OP-'.$i, 'stage' => 'new', 'probability' => 20, 'estimated_value' => 100]);
        }
        Opportunity::create(['person_id' => $other->id, 'title' => 'Ajena', 'code' => 'OP-OTHER', 'stage' => 'new']);
        $this->actingAs(User::factory()->create())->get('/people/'.$person->id.'?tab=opportunities&search=Solar&relation_page=2')->assertOk()->assertInertia(fn (Assert $p) => $p->component('people/show')->where('person.id', $person->id)->where('person.created_at', $person->created_at->toIso8601String())->where('filters.search', 'Solar')->where('customerWorkspace.tab', 'opportunities')->where('customerWorkspace.domains.opportunities.total', 18)->has('customerWorkspace.domains.opportunities.data', 3)->missing('customerWorkspace.domains.quotes'));
        $this->get('/people/'.$person->id.'?tab=unsupported')->assertUnprocessable();
        $this->get('/people?sort=invalid')->assertUnprocessable();
        $staff = Person::create(['category' => 'staff', 'first_name' => 'Equipo']);
        $this->get('/people/'.$staff->id)->assertNotFound();
    }

    public function test_installations_projects_documents_and_activity_use_real_relations(): void
    {
        $person = $this->customer();
        $other = $this->customer();
        $installation = Installation::create(['person_id' => $other->id, 'project_reference' => 'INST-SHARED', 'service_type' => 'solar', 'status' => 'active', 'site_address_line_1' => 'Calle 1']);
        $installation->people()->attach($person, ['role' => 'owner', 'is_primary' => false]);
        $author = User::factory()->create();
        EngineeringDossier::create(['scope' => 'Alcance técnico', 'created_by' => $author->id, 'updated_by' => $author->id, 'installation_id' => $installation->id, 'code' => 'PRO-REAL', 'title' => 'Expediente real', 'business_line' => 'solar', 'kind' => 'project', 'status' => 'draft', 'current_version' => 1]);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'number' => 'WO-REAL', 'title' => 'Trabajo', 'status' => 'planned']);
        $order->attachments()->create(['kind' => 'document', 'original_name' => 'Real.pdf', 'path' => 'private/real.pdf', 'mime_type' => 'application/pdf', 'size' => 1024, 'publication_status' => 'internal']);
        $opp = Opportunity::create(['person_id' => $person->id, 'code' => 'OP-REAL', 'title' => 'Real', 'stage' => 'new']);
        OpportunityEvent::create(['opportunity_id' => $opp->id, 'event_type' => 'note_added', 'summary' => 'Nota real', 'note' => 'Seguimiento comercial', 'occurred_at' => now()]);
        CrmActivity::create(['person_id' => $person->id, 'title' => 'Llamada real', 'type' => 'call', 'status' => 'pending']);
        $this->actingAs(User::factory()->create());
        foreach (['projects', 'installations', 'documents', 'activity'] as $tab) {
            $this->get('/people/'.$person->id.'?tab='.$tab)->assertOk()->assertInertia(fn (Assert $p) => $p->where('customerWorkspace.tab', $tab)->has('customerWorkspace.domains.'.$tab.'.data', $tab === 'activity' ? 3 : 1));
        }
    }

    public function test_last_activity_uses_crm_registration_instead_of_person_updated_at(): void
    {
        $person = $this->customer();
        $activity = CrmActivity::create(['person_id' => $person->id, 'title' => 'Contactar', 'type' => 'call', 'status' => 'pending']);
        $activity->forceFill(['created_at' => now()->subDays(10)])->save();
        $person->update(['email' => 'updated@example.test']);
        $this->actingAs(User::factory()->create())->get('/people')->assertOk()->assertInertia(fn (Assert $p) => $p->where('people.data.0.last_activity_at', fn ($value) => str_contains($value, now()->subDays(10)->toDateString())));
    }

    public function test_duplicate_warning_is_bounded_and_never_merges_profiles(): void
    {
        $person = $this->customer(['email' => 'same@example.test']);
        foreach (range(1, 8) as $i) {
            $this->customer(['email' => 'same@example.test', 'company_name' => 'Posible '.$i]);
        }
        $this->actingAs(User::factory()->create())->get('/people/'.$person->id)->assertOk()->assertInertia(fn (Assert $p) => $p->has('customerWorkspace.possibleDuplicates', 5));
        $this->assertSame(9, Person::query()->count());
    }

    public function test_technician_document_visibility_matches_work_order_access(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        Gate::define('manage-customers', fn () => true);
        $person = $this->customer();
        $installation = Installation::create(['person_id' => $person->id, 'service_type' => 'solar', 'status' => 'active', 'site_address_line_1' => 'Calle 1']);
        foreach ([$technician->id, User::factory()->create(['role' => 'technician'])->id] as $index => $owner) {
            $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $owner, 'number' => 'WO-SCOPE-'.$index, 'title' => 'Trabajo', 'status' => 'planned']);
            $order->attachments()->create(['kind' => 'document', 'original_name' => 'Scope-'.$index.'.pdf', 'path' => 'private/scope.pdf', 'mime_type' => 'application/pdf', 'size' => 1024, 'publication_status' => 'internal']);
        }
        $this->actingAs($technician)->get('/people/'.$person->id.'?tab=documents')->assertOk()->assertInertia(fn (Assert $p) => $p->where('customerWorkspace.domains.documents.total', 1)->where('customerWorkspace.domains.documents.data.0.title', 'Scope-0.pdf'));
    }

    public function test_listing_query_count_does_not_grow_per_customer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->customer();
        $request = Request::create('/people', 'GET', ['per_page' => 50]);
        $request->setUserResolver(fn () => $user);
        $service = app(CustomerWorkspace::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $service->listing($request);
        $small = count(DB::getQueryLog());
        foreach (range(1, 30) as $i) {
            $this->customer(['company_name' => 'Cliente '.$i]);
        }
        DB::flushQueryLog();
        $service->listing($request);
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($small, $large);
        $this->assertLessThanOrEqual(6, $large);
    }
}
