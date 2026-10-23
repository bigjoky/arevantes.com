<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdministrativeRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_and_restore_an_opportunity_archived_by_a_commercial(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Recovery customer']);
        $opportunity = Opportunity::create(['person_id' => $customer->id, 'owner_user_id' => $commercial->id, 'created_by' => $commercial->id, 'code' => 'OPP-2026-00005', 'title' => 'Accidentally archived', 'stage' => 'new', 'probability' => 10, 'estimated_value' => 500]);
        $this->actingAs($commercial)->delete(route('opportunities.destroy', $opportunity))->assertRedirect();
        $this->actingAs($admin)->get(route('opportunities.show', $opportunity))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('opportunities/archived')->where('opportunity.id', $opportunity->id));
        // An archived record can be recovered, not edited through the normal routes.
        $this->patch(route('opportunities.update', $opportunity), ['title' => 'Bypass'])->assertNotFound();
        $this->post(route('opportunities.restore', $opportunity))->assertRedirect(route('opportunities.index'));
        $this->post(route('opportunities.restore', $opportunity))->assertRedirect();
        $this->assertFalse($opportunity->fresh()->trashed());
        $this->assertSame($commercial->id, $opportunity->fresh()->owner_user_id);
        $this->assertSame('new', $opportunity->fresh()->stage);
        $event = $opportunity->events()->where('event_type', 'restored')->sole();
        $this->assertSame($admin->id, $event->actor_id);
        $this->get(route('opportunities.show', $opportunity))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('opportunities/show'));
        $opportunity->delete();
        $this->actingAs(User::factory()->create(['role' => 'technician']))->get(route('opportunities.show', $opportunity))->assertForbidden();
        $this->post(route('opportunities.restore', $opportunity))->assertForbidden();
        $this->assertTrue($opportunity->fresh()->trashed());
    }

    public function test_admin_can_edit_complete_and_delete_another_users_appointment(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $admin = User::factory()->create(['role' => 'admin']);
        $activity = CrmActivity::create(['created_by' => $commercial->id, 'assigned_user_id' => $commercial->id, 'type' => 'meeting', 'title' => 'Customer appointment', 'priority' => 'normal', 'status' => 'pending', 'starts_at' => '2026-09-28 09:00:00', 'due_at' => '2026-09-28 10:00:00']);
        $this->actingAs($admin);
        foreach (['today', 'week', 'month', 'board'] as $view) {
            $this->get(route('operations.activities', ['view' => $view]))->assertOk();
        }
        $this->from(route('operations.activities'))->patch(route('activities.update', $activity), [
            'title' => 'Rescheduled appointment', 'starts_at' => '2026-09-29 10:00:00', 'due_at' => '2026-09-29 11:00:00',
        ])->assertSessionHasNoErrors()->assertRedirect(route('operations.activities'));
        $this->assertSame('Rescheduled appointment', $activity->fresh()->title);
        $this->assertSame('2026-09-29 10:00:00', $activity->fresh()->starts_at->format('Y-m-d H:i:s'));
        $this->patch(route('activities.update', $activity), ['status' => 'completed'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull($activity->fresh()->completed_at);
        $this->delete(route('activities.destroy', $activity))->assertRedirect();
        $this->assertSoftDeleted($activity);
        $this->patch(route('activities.update', $activity), ['title' => 'Deleted appointment'])->assertNotFound();
    }
}
