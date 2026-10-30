<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\Permission;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\User;
use App\Support\WorkspaceNavigation;
use App\Support\WorkspaceShortcuts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceNavigationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // These contract assertions deliberately exercise the optional English UI.
        $this->withSession(['locale' => 'en']);
        app()->setLocale('en');
    }

    public function test_shortcuts_are_personal_authorized_and_cleaned_when_permissions_change(): void
    {
        $user = $this->userWithPermissions(['view-installations']);
        $other = $this->userWithPermissions([]);
        $href = route('engineering.index', absolute: false);
        $this->actingAs($user)->patch(route('workspace.favorite'), ['href' => $href, 'enabled' => true])->assertRedirect();
        $this->actingAs($user)->patch(route('workspace.favorite'), ['href' => $href, 'enabled' => true])->assertRedirect();
        $this->assertSame([$href], $user->fresh()->workspace_shortcuts['favorites']);
        $this->actingAs($other)->patch(route('workspace.favorite'), ['href' => $href, 'enabled' => true])->assertForbidden();
        $this->actingAs($other)->patch(route('workspace.favorite'), ['href' => 'https://example.com', 'enabled' => true])->assertForbidden();
        $this->assertNull($other->fresh()->workspace_shortcuts);

        $user->forceFill(['permission_profile_id' => $other->permission_profile_id])->save();
        $shortcuts = app(WorkspaceShortcuts::class)->for($user->fresh());
        $this->assertSame([], $shortcuts['favorites']);
        $this->assertSame([], $user->fresh()->workspace_shortcuts['favorites']);
    }

    public function test_recent_shortcuts_are_never_recorded_and_legacy_history_is_cleared(): void
    {
        $user = $this->userWithPermissions(['view-installations']);
        $href = route('engineering.index', absolute: false);
        $user->forceFill(['workspace_shortcuts' => ['favorites' => [$href], 'recent' => [$href]]])->save();
        $this->actingAs($user)->get($href, ['Purpose' => 'prefetch'])->assertOk();
        $this->assertSame([], $user->fresh()->workspace_shortcuts['recent']);
        $this->assertSame([$href], $user->fresh()->workspace_shortcuts['favorites']);
        $this->actingAs($user)->get($href)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.shortcuts.recent', []));
        $this->actingAs($user)->patch(route('workspace.favorite'), ['href' => $href, 'enabled' => true])->assertRedirect();
        $this->actingAs($user)->patch(route('workspace.favorite'), ['href' => $href, 'enabled' => false])->assertRedirect();
        $this->assertSame([], $user->fresh()->workspace_shortcuts['favorites']);
        $this->assertSame([], $user->fresh()->workspace_shortcuts['recent']);
    }

    public function test_navigation_only_contains_sections_allowed_by_the_permission_profile(): void
    {
        $user = $this->userWithPermissions(['view-installations']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.abilities.viewInstallations', true)
                ->where('auth.abilities.manageCustomers', false)
                ->where('auth.abilities.manageInstallations', false)
                ->where('auth.abilities.useAi', false)
                ->where('auth.navigation', function (Collection $navigation): bool {
                    return $navigation->pluck('id')->all() === ['home', 'projects', 'company']
                        && collect($navigation->firstWhere('id', 'projects')['children'])->pluck('id')->all() === ['projects.projects', 'projects.installations'];
                }));
    }

    public function test_repeated_permission_checks_eager_load_the_profile_once(): void
    {
        $user = $this->userWithPermissions(['view-installations']);
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        foreach (['view-installations', 'manage-customers', 'manage-quotes', 'view-installations'] as $ability) {
            Gate::forUser($user)->allows($ability);
        }

        $this->assertLessThanOrEqual(4, $queries);
    }

    public function test_public_homepage_does_not_build_workspace_navigation_for_an_authenticated_user(): void
    {
        $user = $this->userWithPermissions(['view-installations']);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.id', $user->id)
                ->where('auth.abilities', [])
                ->where('auth.navigation', [])
                ->where('auth.shortcuts', ['favorites' => [], 'recent' => []])
                ->where('auth.notifications', []));
    }

    public function test_navigation_separates_people_sales_projects_and_inventory_by_business_area(): void
    {
        $user = $this->userWithPermissions([
            'manage-customers',
            'manage-catalog',
            'manage-quotes',
            'manage-installations',
            'view-installations',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.navigation', function (Collection $navigation): bool {
                    return $navigation->pluck('id')->all() === ['home', 'commercial', 'projects', 'operations', 'supply', 'billing', 'company'];
                }));
    }

    public function test_hidden_sections_are_also_forbidden_by_direct_url(): void
    {
        $user = $this->userWithPermissions(['view-installations']);
        $this->actingAs($user);

        $this->get(route('installations.index'))->assertOk();
        $this->get(route('engineering.index'))->assertOk();
        $this->get(route('people.index'))->assertForbidden();
        $this->get(route('purchases.index'))->assertForbidden();
        $this->get(route('finance.index'))->assertForbidden();
        $this->get(route('knowledge.index'))->assertForbidden();
        $this->get(route('assets.index'))->assertForbidden();
        $this->get(route('maintenance.index'))->assertForbidden();
        $this->get(route('contracts-services.index'))->assertForbidden();
        $this->get(route('service-quality.index'))->assertForbidden();
        $this->post(route('geocoding.search'), ['query' => 'Madrid'])->assertForbidden();
    }

    public function test_operations_permissions_expose_each_service_domain_at_the_target_level(): void
    {
        $user = $this->userWithPermissions(['manage-operations']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.navigation', function (Collection $navigation): bool {
                    return $navigation->pluck('id')->all() === ['home', 'projects', 'operations', 'company']
                        && collect($navigation->firstWhere('id', 'operations')['children'])->pluck('id')->all() === ['operations.planning', 'operations.work-orders', 'operations.agenda'];
                }));
    }

    public function test_dashboard_does_not_expose_data_from_unauthorized_sections(): void
    {
        $customer = Person::create([
            'category' => 'customer',
            'first_name' => 'Cliente',
            'email' => 'cliente@example.test',
        ]);
        Installation::create([
            'person_id' => $customer->id,
            'service_type' => 'solar',
            'status' => 'lead',
            'site_address_line_1' => 'Calle Privada 1',
        ]);
        $user = $this->userWithPermissions([]);
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('installations')
                ->where('peopleSummary.total', 0)
                ->where('peopleSummary.customers', 0)
                ->where('installationSummary.total', 0)
                ->where('installationSummary.active', 0)
                ->where('businessSummary.open_opportunities', 0)
                ->where('businessSummary.outstanding_amount', 0)
                ->where('agenda', []));

        $this->getJson(route('dashboard.installation-map'))->assertForbidden();
    }

    public function test_canonical_modules_and_all_sections_are_authorized_real_entry_points(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $navigation = app(WorkspaceNavigation::class)->for($user);
        $this->assertSame(['home', 'commercial', 'projects', 'operations', 'supply', 'billing', 'accounting', 'finance', 'tax', 'company'], array_column($navigation, 'id'));
        $commercial = collect($navigation)->firstWhere('id', 'commercial');
        $this->assertSame('/commercial', $commercial['href']);
        $this->assertTrue($commercial['navigable']);
        $this->assertCount(9, array_filter($navigation, fn ($module) => $module['navigable'] ?? false));
        $expected = [
            'commercial' => ['crm', 'customers', 'quotes'], 'projects' => ['projects', 'installations', 'after-sales'],
            'operations' => ['planning', 'work-orders', 'agenda'], 'supply' => ['products', 'inventory', 'suppliers', 'purchases'],
            'billing' => ['customers', 'suppliers', 'payments'], 'accounting' => ['entries', 'accounts', 'reports', 'books'],
            'finance' => ['treasury', 'banking', 'forecasts'], 'tax' => ['taxes', 'models', 'periods'], 'company' => ['staff', 'documents', 'settings'],
        ];
        foreach ($navigation as $module) {
            if (! isset($module['children'])) {
                continue;
            }
            $this->assertSame(array_map(fn ($id) => $module['id'].'.'.$id, $expected[$module['id']]), array_column($module['children'], 'id'));
            foreach ($module['children'] as $section) {
                $id = substr($section['id'], strlen($module['id']) + 1);
                $this->get(route('workspace.module', ['module' => $module['id'], 'section' => $id]))->assertOk()
                    ->assertInertia(fn (Assert $page) => $section['id'] === 'accounting.books' ? $page->component('accounting/workspace')->where('area', 'books') : $page->component('workspace/module')->where('section.id', $section['id']));
                foreach ($section['links'] as $link) {
                    $route = app('router')->getRoutes()->match(Request::create($link['href'], 'GET'));
                    $this->assertNotNull($route->getName(), $link['href']);
                }
            }
        }
    }

    public function test_reports_and_tools_stay_internal_and_missing_books_are_explicit(): void
    {
        $user = User::factory()->create();
        $navigation = collect(app(WorkspaceNavigation::class)->for($user));
        $accounting = $navigation->firstWhere('id', 'accounting');
        $this->assertCount(4, $accounting['children']);
        $this->assertSame('/accounting?area=overview', $accounting['href']);
        $this->assertTrue($accounting['navigable']);
        $this->assertNotContains('Vista general', array_column($accounting['children'], 'title'));
        $this->actingAs($user)->get($accounting['href'])->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('accounting/workspace')->where('area', 'overview'));
        $restricted = collect(app(WorkspaceNavigation::class)->for($this->userWithPermissions(['view-installations'])));
        $this->assertNull($restricted->firstWhere('id', 'accounting'));
        $reports = collect($accounting['children'])->firstWhere('id', 'accounting.reports');
        $this->assertCount(6, $reports['links']);
        $this->assertStringContainsString('view=ledger', collect($reports['links'])->first(fn ($link) => str_contains($link['href'], 'view=ledger'))['href']);
        $books = collect($accounting['children'])->firstWhere('id', 'accounting.books');
        $this->assertSame([], $books['links']);
        $this->assertNotEmpty($books['pending']);
        $this->actingAs($this->userWithPermissions(['view-installations']))
            ->get('/workspace/modules/accounting/reports')->assertForbidden();
        $this->get('/workspace/modules/company/settings')->assertForbidden();
        $this->get('/workspace/modules/projects/nonexistent')->assertForbidden();
    }

    /** @param array<int, string> $permissionKeys */
    private function userWithPermissions(array $permissionKeys): User
    {
        $profile = PermissionProfile::create([
            'name' => 'Perfil parcial',
            'slug' => 'partial-'.str()->random(8),
            'is_system' => false,
        ]);
        $profile->permissions()->sync(
            Permission::whereIn('key', $permissionKeys)->pluck('id')->all(),
        );

        return User::factory()->create([
            'role' => 'commercial',
            'permission_profile_id' => $profile->id,
        ]);
    }
}
