<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\Domain\CrmServiceProvider;
use App\Providers\Domain\FinanceServiceProvider;
use App\Providers\Domain\OperationsServiceProvider;
use App\Providers\Domain\SupplyServiceProvider;
use App\Support\Authorization\PlatformAbility;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PlatformArchitectureTest extends TestCase
{
    public function test_platform_domain_providers_are_registered(): void
    {
        $providers = [
            AuthorizationServiceProvider::class,
            CrmServiceProvider::class,
            OperationsServiceProvider::class,
            SupplyServiceProvider::class,
            FinanceServiceProvider::class,
        ];

        foreach ($providers as $provider) {
            $this->assertTrue($this->app->providerIsLoaded($provider), "{$provider} is not registered.");
        }
    }

    public function test_domain_route_files_are_loaded_with_their_original_contracts(): void
    {
        $domains = [
            'core' => 'dashboard',
            'crm' => 'people.index',
            'human-resources' => 'human-resources.index',
            'operations' => 'operations.index',
            'supply' => 'purchases.index',
            'finance' => 'finance.index',
            'administration' => 'admin.index',
            'integrations' => 'arevia.conversation',
        ];

        foreach ($domains as $domain => $routeName) {
            $this->assertFileExists(base_path("routes/web/{$domain}.php"));
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "The {$domain} route file was not loaded.");
            $this->assertContains('web', $route->gatherMiddleware());
            $this->assertContains('auth', $route->gatherMiddleware());
        }
    }

    public function test_explicit_route_names_are_unique_across_domain_files(): void
    {
        $names = collect(glob(base_path('routes/web/*.php')))
            ->flatMap(function (string $file): array {
                preg_match_all("/->name\\('([^']+)'\\)/", file_get_contents($file), $matches);

                return $matches[1];
            });

        $this->assertSame($names->count(), $names->unique()->count(), 'Domain route files contain duplicate explicit route names.');
    }

    public function test_every_platform_ability_is_registered_and_follows_the_role_matrix(): void
    {
        $users = [
            'admin' => new User(['role' => 'admin']),
            'commercial' => new User(['role' => 'commercial']),
            'technician' => new User(['role' => 'technician']),
        ];

        foreach (PlatformAbility::cases() as $ability) {
            $this->assertArrayHasKey($ability->value, Gate::abilities());
            $this->assertTrue(Gate::forUser($users['admin'])->allows($ability->value));
            $this->assertSame(
                in_array('commercial', $ability->roles(), true),
                Gate::forUser($users['commercial'])->allows($ability->value),
                "Unexpected commercial access for {$ability->value}.",
            );
            $this->assertSame(
                in_array('technician', $ability->roles(), true),
                Gate::forUser($users['technician'])->allows($ability->value),
                "Unexpected technician access for {$ability->value}.",
            );
        }
    }

    public function test_frontend_ability_keys_are_unique_and_complete(): void
    {
        $keys = collect(PlatformAbility::cases())->map->frontendKey();

        $this->assertCount(19, PlatformAbility::cases());
        $this->assertSame($keys->count(), $keys->unique()->count());
        $this->assertContains('manageCustomers', $keys);
        $this->assertContains('manageStaff', $keys);
        $this->assertContains('viewFinance', $keys);
        $this->assertContains('viewPurchases', $keys);
    }
}
