<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\Permission;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\User;
use App\Support\WorkspaceShortcuts;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                    $items = $navigation->keyBy('title');

                    return $items->keys()->all() === ['Dashboard', 'My HR', 'Engineering', 'Projects']
                        && $items['Engineering']['code'] === 'ENG'
                        && collect($items['Engineering']['children'])->pluck('title')->all() === ['Technical dossiers']
                        && $items['Projects']['code'] === 'PRJ'
                        && collect($items['Projects']['children'])->pluck('title')->all() === ['Installations'];
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
                    $areas = $navigation->filter(fn (mixed $item): bool => isset($item['code']))->keyBy('code');

                    return $areas->keys()->all() === ['PTY', 'CRM', 'SAL', 'ENG', 'PRJ', 'PLN', 'INV', 'BI']
                        && collect($areas['PTY']['children'])->pluck('title')->all() === ['Customers', 'Suppliers']
                        && collect($areas['SAL']['children'])->pluck('title')->all() === ['Proposals', 'Sales documents']
                        && collect($areas['PRJ']['children'])->pluck('title')->all() === ['Installations']
                        && collect($areas['PLN']['children'])->pluck('title')->all() === ['Planning requests']
                        && collect($areas['INV']['children'])->pluck('title')->all() === ['Products & services', 'Stock', 'Reservations', 'Movements', 'Warehouses', 'Configuration', 'Stock counts'];
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
                    $areas = $navigation->filter(fn (mixed $item): bool => isset($item['code']))->keyBy('code');

                    return $areas->keys()->all() === ['PLN', 'OPS', 'AST', 'MNT', 'BI', 'QMS', 'CS', 'CTR']
                        && collect($areas['AST']['children'])->pluck('title')->all() === ['Installed equipment']
                        && collect($areas['MNT']['children'])->pluck('title')->all() === ['Preventive & corrective']
                        && $areas['QMS']['children'][0]['href'] === '/quality/service'
                        && collect($areas['CTR']['children'])->pluck('title')->all() === ['Recurring services'];
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
