<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CrmRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function opportunity(User $user, string $stage = 'new'): Opportunity
    {
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Cliente', 'email' => 'crm-test@example.test']);

        return Opportunity::create(['person_id' => $customer->id, 'owner_user_id' => $user->id, 'title' => 'Proyecto real', 'stage' => $stage, 'estimated_value' => 100, 'probability' => 20]);
    }

    public function test_kanban_limits_rows_but_aggregates_the_complete_filtered_stage(): void
    {
        $user = User::factory()->create();
        for ($index = 0; $index < 30; $index++) {
            $this->opportunity($user);
        }
        $this->actingAs($user)->get('/opportunities')->assertOk()->assertInertia(fn (Assert $page) => $page->has('opportunities', 8)->where('stageTotals.new.count', 30)->where('stageTotals.new.amount', 3000)->has('stageOptions', 7));
        $this->get('/opportunities?page_stage=new&stage_page=3')->assertInertia(fn (Assert $page) => $page->has('opportunities', 16));
        $this->get('/opportunities?view=table')->assertInertia(fn (Assert $page) => $page->has('opportunities', 25)->where('pagination.total', 30)->where('pagination.last_page', 2));
        $this->get('/opportunities?view=table&page=2')->assertInertia(fn (Assert $page) => $page->has('opportunities', 5));
        $this->get('/opportunities?search=crm-test%40example.test')->assertInertia(fn (Assert $page) => $page->where('stageTotals.new.count', 30));
        $this->get('/opportunities?status=won')->assertInertia(fn (Assert $page) => $page->has('opportunities', 0));
    }

    public function test_stage_endpoint_records_history_and_rejects_stale_or_invalid_transitions(): void
    {
        $user = User::factory()->create();
        $item = $this->opportunity($user);
        $this->actingAs($user)->patch("/opportunities/{$item->id}/stage", ['stage' => 'qualified', 'expected_stage' => 'new', 'probability' => 30, 'change_note' => 'Contacto confirmado'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('qualified', $item->fresh()->stage);
        $this->assertDatabaseHas('opportunity_stage_histories', ['opportunity_id' => $item->id, 'from_stage' => 'new', 'to_stage' => 'qualified', 'changed_by' => $user->id]);
        $this->patch("/opportunities/{$item->id}/stage", ['stage' => 'proposal', 'expected_stage' => 'new', 'probability' => 40, 'change_note' => 'Cambio concurrente'])->assertSessionHasErrors('stage');
        $this->assertSame('qualified', $item->fresh()->stage);
        $this->patch("/opportunities/{$item->id}/stage", ['stage' => 'negotiation', 'expected_stage' => 'qualified', 'probability' => 40, 'change_note' => 'Transición no permitida'])->assertSessionHasErrors('stage');
        $this->assertSame('qualified', $item->fresh()->stage);
    }

    public function test_calendar_uses_dated_crm_actions_and_not_undated_or_unrelated_actions(): void
    {
        $user = User::factory()->create();
        $item = $this->opportunity($user);
        CrmActivity::create(['opportunity_id' => $item->id, 'title' => 'Visita real', 'type' => 'visit', 'status' => 'pending', 'due_at' => '2026-10-10']);
        CrmActivity::create(['opportunity_id' => $item->id, 'title' => 'Sin fecha', 'type' => 'call', 'status' => 'pending']);
        $this->actingAs($user)->get('/opportunities?view=calendar&calendar_month=2026-10')->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.view', 'calendar')->has('crmTasks', 1)->where('crmTasks.0.title', 'Visita real')->where('calendarPagination.total', 1));
        $this->get('/opportunities?from=invalid')->assertSessionHasErrors('from');
    }

    public function test_query_count_is_stable_and_edit_deep_link_can_load_an_older_card(): void
    {
        $user = User::factory()->create();
        $old = $this->opportunity($user);
        $old->forceFill(['created_at' => now()->subYear()])->save();
        for ($index = 0; $index < 8; $index++) {
            $this->opportunity($user);
        }
        $this->actingAs($user);
        DB::enableQueryLog();
        $this->get('/opportunities')->assertOk();
        $baseline = count(DB::getQueryLog());
        for ($index = 0; $index < 20; $index++) {
            $this->opportunity($user);
        }
        DB::flushQueryLog();
        $this->get('/opportunities')->assertOk();
        $this->assertLessThanOrEqual($baseline + 2, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->get('/opportunities?opportunity='.$old->id)->assertInertia(fn (Assert $page) => $page->has('opportunities', 9));
        $this->get('/opportunities/'.$old->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('stageOptions.new', 'Nuevo'));
    }

    public function test_crm_access_uses_permission_profile_instead_of_an_invented_manager_role(): void
    {
        $profile = PermissionProfile::create(['name' => 'CRM limitado', 'slug' => 'crm-limited', 'is_system' => false]);
        $profile->permissions()->sync(Permission::where('key', 'manage-customers')->pluck('id'));
        $user = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $profile->id]);
        $this->actingAs($user)->get('/opportunities')->assertOk();
        $profile->permissions()->detach();
        $this->get('/opportunities')->assertForbidden();
    }

    public function test_unauthorized_user_cannot_read_or_move_opportunities(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $item = $this->opportunity($user);
        $this->actingAs($user)->get('/opportunities')->assertForbidden();
        $this->patch("/opportunities/{$item->id}/stage", ['stage' => 'qualified', 'expected_stage' => 'new', 'probability' => 30, 'change_note' => 'Cambio no autorizado'])->assertForbidden();
    }
}
