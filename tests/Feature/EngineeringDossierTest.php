<?php

namespace Tests\Feature;

use App\Models\EngineeringDossier;
use App\Models\FieldServiceProfile;
use App\Models\HrLeaveRequest;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Engineering\EngineeringDossierWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EngineeringDossierTest extends TestCase
{
    use RefreshDatabase;

    public function test_execution_creates_one_native_order_and_retries_return_the_same_order(): void
    {
        [$admin, $technician, $installation, $dossier, $data] = $this->executionFixture();
        $this->actingAs($admin)->post(route('engineering.work-order', $dossier), $data)->assertSessionHasNoErrors();
        $order = WorkOrder::sole();
        $this->assertSame('planned', $order->status);
        $this->assertSame($technician->id, $order->assigned_user_id);
        $this->assertSame(120, $order->estimated_duration_minutes);
        $this->assertSame('scheduled', $installation->fresh()->status);
        $this->assertGreaterThan(0, $order->checklistItems()->count());
        $this->assertSame(1, $order->activities()->count());
        $this->assertSame($order->id, $dossier->scheduleProposals()->first()->work_order_id);
        $this->post(route('engineering.work-order', $dossier), $data)->assertRedirect(route('work-orders.index', ['work_order' => $order->id]));
        $this->assertSame(1, WorkOrder::count());
        $this->assertSame(1, $dossier->events()->where('type', 'work_order_created')->count());
        $this->post(route('engineering.work-order', $dossier), [...$data, 'start_time' => '10:00'])->assertSessionHasErrors('execution');
        $order->update(['status' => 'cancelled']);
        $this->post(route('engineering.work-order', $dossier), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, WorkOrder::count());
    }

    public function test_execution_rechecks_availability_and_financial_controls_after_preview(): void
    {
        [$admin, $technician, $installation, $dossier, $data] = $this->executionFixture();
        $this->actingAs($admin)->getJson(route('engineering.availability', ['engineeringDossier' => $dossier, ...$data]))->assertJsonPath('status', 'clear');
        $profile = FieldServiceProfile::where('user_id', $technician->id)->firstOrFail();
        $profile->update(['is_available' => false]);
        $this->post(route('engineering.work-order', $dossier), $data)->assertSessionHasErrors('execution');
        $this->assertSame(0, WorkOrder::count());
        $profile->update(['is_available' => true]);
        $installation->update(['financial_status' => 'pending']);
        $this->post(route('engineering.work-order', $dossier), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, WorkOrder::count());
        $this->assertNotNull($dossier->scheduleProposals()->first()->work_order_id);
        $this->assertSame('planned', WorkOrder::firstOrFail()->status);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->patch(route('work-orders.update', WorkOrder::firstOrFail()), ['status' => 'in_progress'])->assertSessionHasErrors('financial_status');
        $installation->update(['financial_status' => 'paid']);
        $dossier->update(['scope' => 'Unexpected source mutation']);
        $this->post(route('engineering.work-order', $dossier), $data)->assertSessionHasErrors('expected_fingerprint');
        $restricted = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => PermissionProfile::create(['name' => 'Restricted execution', 'slug' => 'restricted-execution'])->id]);
        $this->actingAs($restricted)->post(route('engineering.work-order', $dossier), $data)->assertForbidden();
    }

    private function executionFixture(): array
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'daily_capacity_minutes' => 480, 'skills' => ['electrical', 'electrical_installation', 'electrical_safety'], 'is_available' => true]);
        $installation = Installation::create(['service_type' => 'electrical', 'status' => 'approved', 'financial_status' => 'paid', 'site_address_line_1' => 'Technical site']);
        $workflow = app(EngineeringDossierWorkflow::class);
        $dossier = $workflow->create($this->payload(null, $installation, ['proposed_solution' => 'Technical design.']), $admin);
        $fingerprint = $dossier->versions()->firstOrFail()->fingerprint;
        $workflow->transition($dossier, 'submit', 1, null, 'Ready for technical review.', $admin);
        $workflow->transition($dossier, 'approve', 1, $fingerprint, 'Technical design approved.', $admin);
        $workflow->planning($dossier, 'request', 1, $fingerprint, 'Please plan this project.', $admin);
        $workflow->planning($dossier, 'accept', 1, $fingerprint, 'Accepted scope for planning.', $admin);
        $data = ['action' => 'propose', 'expected_version' => 1, 'fingerprint' => $fingerprint, 'expected_proposal_id' => 0, 'proposed_on' => today()->addDays(2)->toDateString(), 'assignee_id' => $technician->id, 'estimated_hours' => 2, 'note' => 'Proposed work for execution.'];
        $workflow->schedule($dossier, $data, $admin);
        $proposal = $dossier->scheduleProposals()->firstOrFail();
        $data = [...$data, 'expected_proposal_id' => $proposal->id, 'proposal_fingerprint' => $proposal->fingerprint, 'action' => 'approve', 'start_time' => '09:00'];
        $workflow->schedule($dossier, $data, $admin);

        return [$admin, $technician, $installation, $dossier, $data];
    }

    public function test_new_orders_and_absences_are_rechecked_at_execution(): void
    {
        [$admin, $technician, $installation, $dossier, $data] = $this->executionFixture();
        $this->actingAs($admin)->getJson(route('engineering.availability', ['engineeringDossier' => $dossier, ...$data]))->assertJsonPath('status', 'clear');
        $day = today()->addDays(2);
        $conflict = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'created_by' => $admin->id, 'number' => 'WO-CONFLICT', 'title' => 'New assignment', 'kind' => 'installation', 'status' => 'planned', 'scheduled_start' => $day->copy()->addHours(9), 'scheduled_end' => $day->copy()->addHours(11)]);
        $this->post(route('engineering.work-order', $dossier), $data)->assertSessionHasErrors('execution');
        $conflict->update(['status' => 'cancelled']);
        $person = Person::create(['category' => 'staff', 'first_name' => 'Assignee']);
        $technician->update(['person_id' => $person->id]);
        $leave = HrLeaveRequest::create(['person_id' => $person->id, 'type' => 'vacation', 'start_date' => $day, 'end_date' => $day, 'status' => 'pending', 'requested_days' => 1]);
        $this->post(route('engineering.work-order', $dossier), $data)->assertSessionHasErrors('execution');
        $leave->update(['status' => 'approved']);
        $this->post(route('engineering.work-order', $dossier), $data)->assertSessionHasErrors('execution');
        $leave->update(['status' => 'rejected']);
        $this->post(route('engineering.work-order', $dossier), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, WorkOrder::where('status', 'planned')->count());
    }

    public function test_scheduling_proposals_preserve_rejected_versions_and_require_fresh_approval(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create(['service_type' => 'electrical', 'status' => 'survey_booked', 'site_address_line_1' => 'Technical site']);
        $workflow = app(EngineeringDossierWorkflow::class);
        $dossier = $workflow->create($this->payload(null, $installation, ['proposed_solution' => 'Technical design.']), $admin);
        $fingerprint = $dossier->versions()->firstOrFail()->fingerprint;
        $workflow->transition($dossier, 'submit', 1, null, 'Ready for technical review.', $admin);
        $workflow->transition($dossier, 'approve', 1, $fingerprint, 'Technical design approved.', $admin);
        $data = ['action' => 'propose', 'expected_version' => 1, 'fingerprint' => $fingerprint, 'expected_proposal_id' => 0,
            'proposed_on' => today()->addDays(2)->toDateString(), 'assignee_id' => $technician->id, 'estimated_hours' => 6.5, 'note' => 'Proposed work date and effort for review.'];
        $this->actingAs($admin)->patch(route('engineering.schedule', $dossier), $data)->assertSessionHasErrors('schedule');
        $workflow->planning($dossier, 'request', 1, $fingerprint, 'Please plan this project.', $admin);
        $workflow->planning($dossier, 'accept', 1, $fingerprint, 'Accepted scope for planning.', $admin);
        $this->patch(route('engineering.schedule', $dossier), [...$data, 'estimated_hours' => 0])->assertSessionHasErrors('estimated_hours');
        $this->patch(route('engineering.schedule', $dossier), [...$data, 'proposed_on' => today()->subDay()->toDateString()])->assertSessionHasErrors('proposed_on');
        $this->patch(route('engineering.schedule', $dossier), $data)->assertSessionHasNoErrors();
        $first = $dossier->scheduleProposals()->firstOrFail();
        $this->patch(route('engineering.schedule', $dossier), $data)->assertSessionHasErrors('schedule');
        $review = [...$data, 'action' => 'approve', 'expected_proposal_id' => $first->id, 'proposal_fingerprint' => $first->fingerprint];
        $this->travel(3)->days();
        $this->patch(route('engineering.schedule', $dossier), $review)->assertSessionHasErrors('schedule');
        $this->travelBack();
        $first->update(['estimated_hours' => 9]);
        $this->patch(route('engineering.schedule', $dossier), $review)->assertSessionHasErrors('schedule');
        $first->update(['estimated_hours' => 6.5]);
        $this->patch(route('engineering.schedule', $dossier), [...$review, 'action' => 'decline'])->assertSessionHasNoErrors();
        $this->patch(route('engineering.schedule', $dossier), [...$data, 'expected_proposal_id' => $first->id, 'estimated_hours' => 8])->assertSessionHasNoErrors();
        $latest = $dossier->scheduleProposals()->firstOrFail();
        $this->assertNotSame($first->id, $latest->id);
        $this->assertSame('declined', $first->fresh()->status);
        $this->patch(route('engineering.schedule', $dossier), $review)->assertSessionHasErrors('schedule');
        $this->patch(route('engineering.schedule', $dossier), [...$review, 'expected_proposal_id' => $latest->id, 'proposal_fingerprint' => $latest->fingerprint])->assertSessionHasNoErrors();
        $this->assertSame('approved', $latest->fresh()->status);
        $this->assertSame($admin->id, $latest->fresh()->reviewed_by);
        $availability = ['expected_version' => 1, 'fingerprint' => $fingerprint, 'expected_proposal_id' => $latest->id, 'proposal_fingerprint' => $latest->fingerprint, 'start_time' => '09:00'];
        $this->getJson(route('engineering.availability', ['engineeringDossier' => $dossier, ...$availability]))->assertOk()->assertJsonPath('status', 'blocked');
        $this->getJson(route('engineering.availability', ['engineeringDossier' => $dossier, ...$availability, 'proposal_fingerprint' => str_repeat('0', 64)]))->assertUnprocessable();
        $this->patch(route('engineering.schedule', $dossier), [...$data, 'expected_proposal_id' => $latest->id])->assertSessionHasErrors('schedule');
        $this->assertSame(2, $dossier->scheduleProposals()->count());
        $this->assertSame(4, $dossier->events()->where('type', 'like', 'schedule_%')->count());
        $this->assertSame(0, WorkOrder::count());
        $this->assertSame('survey_booked', $installation->fresh()->status);
        $this->get(route('planning-scenario-requests.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('technicalRequests.0.schedule_proposals', 2));
        $restrictedProfile = PermissionProfile::create(['name' => 'No scheduling access', 'slug' => 'no-scheduling']);
        $restricted = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $restrictedProfile->id]);
        $this->actingAs($restricted)->patch(route('engineering.schedule', $dossier), $data)->assertForbidden();
        $this->getJson(route('engineering.availability', ['engineeringDossier' => $dossier, ...$availability]))->assertForbidden();
    }

    public function test_approved_engineering_can_request_planning_and_receive_an_audited_decision(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $installation = Installation::create(['service_type' => 'electrical', 'status' => 'survey_booked', 'site_address_line_1' => 'Technical site']);
        $workflow = app(EngineeringDossierWorkflow::class);
        $dossier = $workflow->create($this->payload(null, $installation, ['proposed_solution' => 'Approved installation design.']), $admin);
        $fingerprint = $dossier->versions()->firstOrFail()->fingerprint;
        $data = ['action' => 'request', 'expected_version' => 1, 'fingerprint' => $fingerprint, 'note' => 'Please review capacity for this approved design.'];
        $this->actingAs($admin)->patch(route('engineering.planning', $dossier), $data)->assertSessionHasErrors('planning');
        $workflow->transition($dossier, 'submit', 1, null, 'Ready for technical review.', $admin);
        $workflow->transition($dossier, 'approve', 1, $fingerprint, 'Technical design approved.', $admin);

        $this->patch(route('engineering.planning', $dossier), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->patch(route('engineering.planning', $dossier), $data)->assertSessionHasErrors('planning');
        $this->assertSame('pending', $dossier->fresh()->planning_status);
        $this->get(route('planning-scenario-requests.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('technicalRequests', 1)->where('technicalRequests.0.id', $dossier->id));

        $this->patch(route('engineering.planning', $dossier), [...$data, 'action' => 'accept', 'fingerprint' => str_repeat('0', 64)])->assertSessionHasErrors('expected_fingerprint');
        $dossier->update(['scope' => 'Unexpected out-of-band change']);
        $this->patch(route('engineering.planning', $dossier), [...$data, 'action' => 'accept'])->assertSessionHasErrors('expected_fingerprint');
        $dossier->update(['scope' => $this->payload(null, $installation)['scope']]);
        $this->patch(route('engineering.planning', $dossier), [...$data, 'action' => 'accept'])->assertSessionHasNoErrors();
        $this->patch(route('engineering.planning', $dossier), [...$data, 'action' => 'decline'])->assertSessionHasErrors('planning');
        $this->assertSame('accepted', $dossier->fresh()->planning_status);
        $this->assertSame($admin->id, $dossier->fresh()->planning_reviewed_by);
        $this->assertSame(2, $dossier->events()->whereIn('type', ['planning_request', 'planning_accept'])->count());
        $this->assertSame('approved', $dossier->fresh()->status);
        $this->assertSame('survey_booked', $installation->fresh()->status);
        $this->assertSame(0, WorkOrder::count());
    }

    public function test_planning_intake_enforces_permissions_and_records_declines(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = PermissionProfile::create(['name' => 'Engineering only', 'slug' => 'engineering-only']);
        $permission = Permission::firstOrCreate(['key' => 'manage-installations'], ['name' => 'Manage installations']);
        $profile->permissions()->attach($permission->id);
        $engineer = User::factory()->create(['role' => 'commercial', 'permission_profile_id' => $profile->id]);
        $installation = Installation::create(['service_type' => 'electrical', 'status' => 'survey_booked', 'site_address_line_1' => 'Technical site']);
        $workflow = app(EngineeringDossierWorkflow::class);
        $dossier = $workflow->create($this->payload(null, $installation, ['proposed_solution' => 'Technical design.']), $admin);
        $fingerprint = $dossier->versions()->firstOrFail()->fingerprint;
        $workflow->transition($dossier, 'submit', 1, null, 'Ready for technical review.', $admin);
        $workflow->transition($dossier, 'approve', 1, $fingerprint, 'Technical design approved.', $admin);
        $data = ['action' => 'request', 'expected_version' => 1, 'fingerprint' => $fingerprint, 'note' => 'Review this technical scope for scheduling.'];
        $this->actingAs($engineer)->patch(route('engineering.planning', $dossier), $data)->assertSessionHasNoErrors();
        $this->patch(route('engineering.planning', $dossier), [...$data, 'action' => 'accept'])->assertForbidden();
        $this->actingAs($admin)->patch(route('engineering.planning', $dossier), [...$data, 'action' => 'decline'])->assertSessionHasNoErrors();
        $this->assertSame('declined', $dossier->fresh()->planning_status);
        $this->assertNotNull($dossier->fresh()->planning_reviewed_at);
    }

    public function test_a_technical_dossier_is_versioned_approved_and_handed_over_without_committing_execution(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $installation = Installation::create([
            'service_type' => 'electrical',
            'status' => 'survey_booked',
            'project_reference' => 'ELEC-ENG-1',
            'site_address_line_1' => 'Calle Técnica 1',
        ]);
        $opportunity = Opportunity::create([
            'installation_id' => $installation->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'code' => 'OPP-ENG-1',
            'title' => 'Electrical refurbishment',
            'stage' => 'survey',
        ]);
        $this->actingAs($admin);

        $this->post(route('engineering.store'), $this->payload($opportunity, $installation, [
            'proposed_solution' => 'Replace the main board and separate critical circuits.',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $dossier = EngineeringDossier::firstOrFail();
        $this->assertSame('ENG-2026-000001', $dossier->code);
        $this->assertSame(1, $dossier->current_version);
        $this->assertSame('draft', $dossier->status);
        $this->assertCount(1, $dossier->versions);
        $this->assertCount(1, $dossier->events);

        $this->patch(route('engineering.update', $dossier), $this->payload($opportunity, $installation, [
            'expected_version' => 1,
            'proposed_solution' => 'Replace the main board, separate critical circuits and add surge protection.',
            'change_note' => 'Surge protection added after the site risk review.',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $dossier->refresh();
        $this->assertSame(2, $dossier->current_version);
        $this->assertCount(2, $dossier->versions);
        $this->assertSame('Replace the main board and separate critical circuits.', $dossier->versions()->where('version', 1)->sole()->snapshot['proposed_solution']);
        $fingerprint = $dossier->versions()->where('version', 2)->sole()->fingerprint;

        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'submit', 'expected_version' => 2, 'note' => 'Technical evidence is complete and ready for independent review.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'approve', 'expected_version' => 2, 'expected_fingerprint' => $fingerprint,
            'note' => 'Calculation, scope and applicable standards have been reviewed.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $dossier->refresh()->status);
        $this->assertSame($admin->id, $dossier->approved_by);

        $this->patch(route('engineering.update', $dossier), $this->payload($opportunity, $installation, [
            'expected_version' => 2, 'change_note' => 'Attempt to alter an approved version.',
        ]))->assertSessionHasErrors('status');

        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'handover', 'expected_version' => 2, 'expected_fingerprint' => $fingerprint,
            'note' => 'Approved version handed to the project team for execution planning.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $dossier->refresh();
        $this->assertSame('handed_over', $dossier->status);
        $this->assertSame($admin->id, $dossier->handed_over_by);
        $this->assertSame('survey_booked', $installation->fresh()->status);
        $this->assertSame(0, WorkOrder::count());
        $this->assertCount(5, $dossier->events);

        $this->get(route('engineering.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('engineering/index')
            ->where('dossiers.0.code', 'ENG-2026-000001')
            ->where('dossiers.0.status', 'handed_over')
            ->where('dossiers.0.current_fingerprint', $fingerprint)
            ->has('dossiers.0.versions', 2)
            ->has('dossiers.0.events', 5));
        $this->getJson(route('global-search', ['q' => 'ENG-2026']))
            ->assertOk()
            ->assertJsonPath('results.0.group', 'Engineering')
            ->assertJsonPath('results.0.href', '/engineering#dossier-'.$dossier->id);
    }

    public function test_stale_or_unlinked_dossiers_cannot_be_approved_or_handed_over(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $opportunity = Opportunity::create([
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'code' => 'OPP-ENG-2',
            'title' => 'Security study',
            'stage' => 'survey',
        ]);
        $this->actingAs($admin)->post(route('engineering.store'), $this->payload($opportunity, null, [
            'business_line' => 'security',
            'proposed_solution' => 'Grade 2 alarm with perimeter detection.',
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $dossier = EngineeringDossier::firstOrFail();
        $fingerprint = $dossier->versions()->sole()->fingerprint;

        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'submit', 'expected_version' => 1, 'note' => 'Evidence prepared for technical review.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'approve', 'expected_version' => 1, 'expected_fingerprint' => str_repeat('0', 64),
            'note' => 'Attempt to approve a stale technical snapshot.',
        ])->assertSessionHasErrors('expected_fingerprint');
        $originalTitle = $dossier->title;
        $dossier->update(['title' => 'Content altered outside its governed version']);
        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'approve', 'expected_version' => 1, 'expected_fingerprint' => $fingerprint,
            'note' => 'Attempt to approve content that no longer matches its version.',
        ])->assertSessionHasErrors('expected_fingerprint');
        $dossier->update(['title' => $originalTitle]);
        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'approve', 'expected_version' => 1, 'expected_fingerprint' => $fingerprint,
            'note' => 'Current technical snapshot reviewed and approved.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('engineering.transition', $dossier), [
            'action' => 'handover', 'expected_version' => 1, 'expected_fingerprint' => $fingerprint,
            'note' => 'Attempt to hand over without an execution project.',
        ])->assertSessionHasErrors('installation_id');
        $this->assertSame('approved', $dossier->fresh()->status);
    }

    public function test_technicians_can_consult_engineering_but_cannot_change_it(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create([
            'service_type' => 'solar',
            'status' => 'survey_booked',
            'site_address_line_1' => 'Calle Solar 1',
        ]);

        $this->actingAs($technician)->get(route('engineering.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canManage', false));
        $this->post(route('engineering.store'), $this->payload(null, $installation))->assertForbidden();
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(?Opportunity $opportunity, ?Installation $installation, array $overrides = []): array
    {
        return [
            'opportunity_id' => $opportunity?->id,
            'installation_id' => $installation?->id,
            'title' => 'Main technical study',
            'business_line' => 'electrical',
            'kind' => 'design',
            'scope' => 'Define the safe execution scope and technical acceptance criteria.',
            'site_conditions' => 'Existing low-voltage installation in service.',
            'calculations' => 'Demand, voltage drop and protection coordination checked.',
            'proposed_solution' => null,
            'risks' => 'Live installation and continuity constraints.',
            'standards' => 'REBT and applicable UNE standards.',
            'conclusions' => 'Execution is feasible under the recorded conditions.',
            ...$overrides,
        ];
    }
}
