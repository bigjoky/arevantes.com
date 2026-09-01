<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\Permission;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceNavigationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

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

                    return $items->keys()->all() === ['Dashboard', 'My HR', 'Projects']
                        && $items['Projects']['code'] === 'PRJ'
                        && collect($items['Projects']['children'])->pluck('title')->all() === ['Installations'];
                }));
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

                    return $areas->keys()->all() === ['PTY', 'CRM', 'SAL', 'PRJ', 'PLN', 'INV', 'BI']
                        && collect($areas['PTY']['children'])->pluck('title')->all() === ['Customers', 'Suppliers']
                        && collect($areas['SAL']['children'])->pluck('title')->all() === ['Proposals', 'Sales documents']
                        && collect($areas['PRJ']['children'])->pluck('title')->all() === ['Installations']
                        && collect($areas['PLN']['children'])->pluck('title')->all() === ['Planning requests']
                        && collect($areas['INV']['children'])->pluck('title')->all() === ['Products & services', 'Inventory', 'Stock counts'];
                }));
    }

    public function test_hidden_sections_are_also_forbidden_by_direct_url(): void
    {
        $user = $this->userWithPermissions(['view-installations']);
        $this->actingAs($user);

        $this->get(route('installations.index'))->assertOk();
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
