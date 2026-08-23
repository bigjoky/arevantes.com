<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HumanResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_visit_the_human_resources_overview(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staffWithAccess = Person::create([
            'category' => 'staff',
            'first_name' => 'Lucía',
            'last_name' => 'García',
            'email' => 'lucia@example.com',
            'staff_role' => 'Técnica',
        ]);
        $staffWithoutAccess = Person::create([
            'category' => 'staff',
            'first_name' => 'Mario',
            'last_name' => 'Ruiz',
            'email' => 'mario@example.com',
            'staff_role' => 'Comercial',
        ]);
        User::factory()->create([
            'person_id' => $staffWithAccess->id,
            'role' => 'technician',
        ]);

        $this->actingAs($admin)
            ->get(route('human-resources.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('human-resources/index')
                ->where('summary.total', 2)
                ->where('summary.withAccess', 1)
                ->where('summary.withoutAccess', 1)
                ->where('summary.roles', 2)
                ->where('roleDistribution.0.count', 1)
                ->has('recentStaff', 2)
                ->where('recentStaff.0.id', $staffWithoutAccess->id)
                ->where('recentStaff.1.name', 'Lucía García')
                ->where('recentStaff.1.accessRole', 'technician')
                ->where('capacityForecast.period.weeks', 6)
                ->has('capacityForecast.weeks', 6)
            );
    }

    public function test_non_admin_cannot_visit_the_human_resources_overview(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'commercial']))
            ->get(route('human-resources.index'))
            ->assertForbidden();
    }
}
