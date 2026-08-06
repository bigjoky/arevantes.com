<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstallationPeopleTest extends TestCase
{
    use RefreshDatabase;

    public function test_installation_can_have_multiple_people_and_is_visible_from_each_crm_profile(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $primary = Person::create(['category' => 'customer', 'first_name' => 'Ana', 'last_name' => 'Principal']);
        $coOwner = Person::create(['category' => 'customer', 'first_name' => 'Luis', 'last_name' => 'Copropietario']);
        $installation = Installation::create([
            'person_id' => $primary->id,
            'service_type' => 'solar',
            'status' => 'active',
            'project_reference' => 'MULTI-OWNER-1',
            'site_address_line_1' => 'Calle Compartida 1',
        ]);

        $this->assertDatabaseHas('installation_person', [
            'installation_id' => $installation->id,
            'person_id' => $primary->id,
            'role' => 'owner',
            'is_primary' => true,
        ]);

        $this->actingAs($user)->post(route('installations.people.store', $installation), [
            'person_id' => $coOwner->id,
            'role' => 'co_owner',
            'ownership_percentage' => 50,
        ])->assertRedirect();

        $this->assertDatabaseHas('installation_person', [
            'installation_id' => $installation->id,
            'person_id' => $coOwner->id,
            'role' => 'co_owner',
            'ownership_percentage' => 50,
            'is_primary' => false,
        ]);

        $this->get(route('installations.show', $installation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('installations/show')
                ->has('installation.people', 2)
                ->where('installation.people.0.id', $primary->id)
                ->where('installation.people.1.id', $coOwner->id));

        $this->get(route('people.show', $coOwner))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('person.installations', 1)
                ->where('person.installations.0.id', $installation->id)
                ->where('person.installations.0.relationship', 'co_owner'));
    }

    public function test_primary_owner_cannot_be_removed_but_an_additional_person_can(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $primary = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $coOwner = Person::create(['category' => 'customer', 'first_name' => 'Luis']);
        $installation = Installation::create(['person_id' => $primary->id, 'service_type' => 'solar', 'status' => 'active', 'site_address_line_1' => 'Calle Sol']);
        $installation->people()->attach($coOwner->id, ['role' => 'co_owner']);

        $this->actingAs($user)->delete(route('installations.people.destroy', [$installation, $primary]))->assertStatus(422);
        $this->delete(route('installations.people.destroy', [$installation, $coOwner]))->assertRedirect();

        $this->assertDatabaseHas('installation_person', ['installation_id' => $installation->id, 'person_id' => $primary->id]);
        $this->assertDatabaseMissing('installation_person', ['installation_id' => $installation->id, 'person_id' => $coOwner->id]);
    }

    public function test_changing_the_primary_customer_keeps_the_previous_person_as_co_owner(): void
    {
        $firstOwner = Person::create(['category' => 'customer', 'first_name' => 'Ana']);
        $newOwner = Person::create(['category' => 'customer', 'first_name' => 'Luis']);
        $installation = Installation::create(['person_id' => $firstOwner->id, 'service_type' => 'solar', 'status' => 'active', 'site_address_line_1' => 'Calle Sol']);

        $installation->update(['person_id' => $newOwner->id]);

        $this->assertDatabaseHas('installation_person', [
            'installation_id' => $installation->id,
            'person_id' => $newOwner->id,
            'role' => 'owner',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('installation_person', [
            'installation_id' => $installation->id,
            'person_id' => $firstOwner->id,
            'role' => 'co_owner',
            'is_primary' => false,
        ]);
    }
}
