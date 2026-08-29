<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\InstallationAsset;
use App\Models\MaintenancePlan;
use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\Product;
use App\Models\ReplenishmentTarget;
use App\Models\ServiceCorrectivePlan;
use App\Models\ServiceCorrectiveProposal;
use App\Models\ServiceCycleCommitment;
use App\Models\ServiceCycleDeviation;
use App\Models\ServiceTicket;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use App\Services\AfterSales\RecurringServiceQualityDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RecurringServiceQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_cycle_detection_is_idempotent_and_opens_assigned_reviews_by_business_line(): void
    {
        [$admin, $technician, $commitment, $asset] = $this->completedSecurityCycle();

        $detector = app(RecurringServiceQualityDetector::class);
        $this->assertCount(6, $detector->detect($commitment));
        $this->assertCount(6, $detector->detect($commitment->refresh()));
        $this->assertSame(6, ServiceCycleDeviation::count());
        $this->assertDatabaseHas('service_cycle_deviations', [
            'service_cycle_commitment_id' => $commitment->id,
            'business_line' => 'security',
            'category' => 'duration',
            'status' => 'open',
            'assigned_to' => $technician->id,
        ]);
        $this->assertDatabaseHas('service_cycle_deviation_assets', ['installation_asset_id' => $asset->id]);
        $this->assertSame(6, ServiceCycleDeviation::withCount('events')->get()->sum('events_count'));

        $this->actingAs($admin)->get(route('after-sales.quality.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('after-sales/quality')
                ->has('deviations', 6)
                ->where('summary.open', 6)
                ->where('summary.affected_assets', 0)
                ->where('deviations.0.business_line', 'security'));
    }

    public function test_quality_review_requires_valid_transitions_and_only_confirmed_findings_feed_health(): void
    {
        [$admin, , $commitment, $asset] = $this->completedSecurityCycle();
        app(RecurringServiceQualityDetector::class)->detect($commitment);
        $deviation = ServiceCycleDeviation::where('category', 'duration')->sole();

        $this->actingAs($admin)->patch(route('after-sales.quality.review', $deviation), [
            'action' => 'confirm',
            'assigned_to' => $admin->id,
            'review_due_on' => today()->addWeek()->toDateString(),
            'note' => 'Execution evidence confirms a material duration overrun.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('service_cycle_deviations', [
            'id' => $deviation->id, 'status' => 'confirmed', 'confirmed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('service_cycle_deviation_events', [
            'service_cycle_deviation_id' => $deviation->id, 'event' => 'confirm',
            'from_status' => 'open', 'to_status' => 'confirmed', 'actor_id' => $admin->id,
        ]);
        $this->actingAs($admin)->get(route('after-sales.quality.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.confirmed', 1)
                ->where('summary.affected_assets', 1)
                ->where('summary.quality_score', 88));

        $this->patch(route('after-sales.quality.review', $deviation), [
            'action' => 'dismiss', 'note' => 'This transition must not be accepted.',
        ])->assertSessionHasErrors('action');
        $this->assertSame('confirmed', $deviation->refresh()->status);
        $this->assertTrue($deviation->assets()->whereKey($asset->id)->exists());

        $this->patch(route('after-sales.quality.review', $deviation), [
            'action' => 'resolve',
            'note' => 'Operational coaching completed and the next visit will be reviewed.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('resolved', $deviation->refresh()->status);
    }

    public function test_confirmed_deviations_become_a_traceable_corrective_plan_with_normalized_effectiveness(): void
    {
        [$admin, $technician, $commitment] = $this->completedSecurityCycle();
        app(RecurringServiceQualityDetector::class)->detect($commitment);
        $duration = ServiceCycleDeviation::where('category', 'duration')->sole();
        $travel = ServiceCycleDeviation::where('category', 'travel')->sole();
        foreach ([$duration, $travel] as $deviation) {
            $this->actingAs($admin)->patch(route('after-sales.quality.review', $deviation), [
                'action' => 'confirm', 'assigned_to' => $technician->id,
                'review_due_on' => today()->addWeek()->toDateString(),
                'note' => 'Execution records confirm this deviation and require corrective analysis.',
            ])->assertRedirect();
        }

        $this->post(route('after-sales.quality.corrective-plans.store', $duration), [
            'deviation_ids' => [$duration->id, $travel->id],
            'title' => 'Reduce security service overruns',
            'root_cause_category' => 'process',
            'root_cause_analysis' => 'The visit sequence repeats diagnosis and travel preparation without a shared pre-visit check.',
            'owner_id' => $admin->id, 'target_date' => today()->addMonth()->toDateString(),
            'verification_cycles' => 1, 'action_type' => 'corrective',
            'action_title' => 'Introduce a pre-visit readiness review',
            'action_details' => 'Confirm route, remote diagnosis and required evidence before dispatch.',
            'action_owner_id' => $technician->id, 'action_due_on' => today()->addWeek()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $plan = ServiceCorrectivePlan::with(['actions', 'deviations', 'events'])->sole();
        $this->assertSame('draft', $plan->status);
        $this->assertStringStartsWith('CAP-2026-', $plan->code);
        $this->assertCount(2, $plan->deviations);
        $this->assertSame(['duration', 'travel'], $plan->baseline_snapshot['categories']);
        $this->assertEquals(1.0, $plan->baseline_snapshot['deviation_rate']);
        $this->assertSame('created', $plan->events->first()->event);

        $this->patch(route('after-sales.quality.corrective-plans.transition', $plan), [
            'action' => 'activate', 'note' => 'Root cause and responsible action have been reviewed.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $action = $plan->actions->first();
        $this->patch(route('after-sales.quality.corrective-actions.update', $action), [
            'status' => 'completed', 'completion_note' => 'Readiness checklist piloted and signed by the service lead.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('after-sales.quality.corrective-plans.proposals.store', $plan), [
            'type' => 'work_order_template', 'title' => 'Add readiness checkpoint',
            'rationale' => 'The corrective trial removed duplicate diagnosis work.',
            'proposed_change' => 'Add a mandatory pre-visit readiness item to the security maintenance template.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('service_corrective_proposals', ['service_corrective_plan_id' => $plan->id, 'status' => 'prepared']);
        $proposal = ServiceCorrectiveProposal::sole();
        $this->assertSame('work_order_template', $proposal->target_type);
        $this->assertNotNull($proposal->target_snapshot['fingerprint']);
        $this->patch(route('after-sales.quality.corrective-proposals.decision', $proposal), [
            'action' => 'approve', 'note' => 'Operations approves this proposal for a separate controlled application.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $proposal->refresh()->status);
        $this->post(route('improvement-decisions.apply', $proposal), [
            'confirmed' => '1',
            'application_note' => 'This controlled attempt intentionally lacks its required structured checklist item.',
        ])->assertSessionHasErrors('checklist_label');
        $this->assertDatabaseHas('service_corrective_proposal_applications', [
            'service_corrective_proposal_id' => $proposal->id, 'status' => 'failed',
        ]);
        $this->post(route('improvement-decisions.apply', $proposal), [
            'confirmed' => '1',
            'application_note' => 'The approved readiness checkpoint is safe to add to future work orders.',
            'checklist_label' => 'Confirm route, remote diagnosis and required evidence before dispatch.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('applied', $proposal->refresh()->status);
        $template = WorkOrderTemplate::findOrFail($plan->maintenancePlan->work_order_template_id);
        $this->assertCount(2, $template->checklist);
        $this->post(route('improvement-decisions.apply', $proposal), [
            'confirmed' => '1',
            'application_note' => 'This is an idempotent retry of the already completed controlled application.',
            'checklist_label' => 'Confirm route, remote diagnosis and required evidence before dispatch.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(2, $template->refresh()->checklist);
        $this->assertDatabaseHas('service_corrective_proposal_applications', ['service_corrective_proposal_id' => $proposal->id, 'status' => 'replayed']);

        $this->post(route('after-sales.quality.corrective-plans.proposals.store', $plan), [
            'type' => 'maintenance_scope', 'title' => 'Align the recurring scope',
            'rationale' => 'The readiness control should be visible in the contractual service scope.',
            'proposed_change' => 'Include readiness confirmation in the governed maintenance scope.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $scopeProposal = ServiceCorrectiveProposal::where('type', 'maintenance_scope')->sole();
        $maintenancePlan = $plan->maintenancePlan;
        $maintenancePlan->update(['version' => $maintenancePlan->version + 1, 'scope' => 'Scope changed after proposal preparation.']);

        $this->patch(route('after-sales.quality.corrective-proposals.decision', $scopeProposal), [
            'action' => 'approve', 'note' => 'This stale proposal must not be approved against a later plan version.',
        ])->assertSessionHasErrors('action');
        $this->assertSame('prepared', $scopeProposal->refresh()->status);
        $this->patch(route('after-sales.quality.corrective-proposals.decision', $scopeProposal), [
            'action' => 'return', 'note' => 'The maintenance scope changed and the proposal must be reconciled with it.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('after-sales.quality.corrective-proposals.decision', $scopeProposal), [
            'action' => 'resubmit', 'note' => 'The proposed wording now reflects the current governed maintenance scope.',
            'proposed_change' => 'Add readiness confirmation to the current maintenance scope version.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $scopeProposal->refresh()->resubmission_count);
        $this->assertSame((string) $maintenancePlan->refresh()->version, $scopeProposal->target_version);
        $this->patch(route('after-sales.quality.corrective-proposals.decision', $scopeProposal), [
            'action' => 'approve', 'note' => 'Operations approves the reconciled proposal for separate application.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $scopeProposal->refresh()->status);
        $this->post(route('improvement-decisions.apply', $scopeProposal), [
            'confirmed' => '1',
            'application_note' => 'The reconciled scope addition is approved for the next governed plan version.',
            'scope_addition' => 'Add readiness confirmation to the current maintenance scope version.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('applied', $scopeProposal->refresh()->status);
        $this->assertStringContainsString('Add readiness confirmation', $maintenancePlan->refresh()->scope);

        $this->post(route('after-sales.quality.corrective-plans.proposals.store', $plan), [
            'type' => 'preventive_stock', 'title' => 'Review readiness spare stock',
            'rationale' => 'The corrective review identified a possible dependency on prepared spare parts.',
            'proposed_change' => 'Review minimum preventive quantities without reserving or purchasing stock.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $stockProposal = ServiceCorrectiveProposal::where('type', 'preventive_stock')->sole();
        $this->actingAs($technician)->patch(route('after-sales.quality.corrective-proposals.decision', $stockProposal), [
            'action' => 'approve', 'note' => 'A technician cannot approve a supply-owned improvement proposal.',
        ])->assertForbidden();
        $this->assertSame('prepared', $stockProposal->refresh()->status);
        $purchaser = User::factory()->create([
            'role' => 'commercial',
            'permission_profile_id' => PermissionProfile::where('slug', 'purchasing')->firstOrFail()->id,
        ]);
        $this->actingAs($purchaser)->get(route('after-sales.quality.index'))->assertForbidden();
        $this->get(route('improvement-decisions.index'))->assertInertia(fn (Assert $page) => $page
            ->component('after-sales/improvements')
            ->has('proposals.data', 1)
            ->where('proposals.data.0.id', $stockProposal->id)
            ->where('proposals.data.0.can_decide', true)
            ->where('summary.open', 1));
        $this->getJson(route('action-center'))
            ->assertOk()
            ->assertJsonFragment(['label' => 'Improvement decisions'])
            ->assertJsonFragment(['href' => '/improvement-decisions?status=open#proposal-'.$stockProposal->id]);
        $this->patch(route('after-sales.quality.corrective-proposals.decision', $stockProposal), [
            'action' => 'approve', 'note' => 'Supply approves the stock review for a separate controlled application.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $stockProposal->refresh()->status);
        $product = Product::where('sku', 'SEC-PREV-1')->sole();
        $this->getJson(route('action-center'))
            ->assertJsonFragment(['href' => '/improvement-decisions?status=approved#proposal-'.$stockProposal->id]);
        $this->actingAs($technician)->post(route('improvement-decisions.apply', $stockProposal), [
            'confirmed' => '1', 'application_note' => 'A technical profile cannot apply a supply-owned objective.',
            'product_id' => $product->id, 'target_quantity' => 10,
        ])->assertForbidden();
        $this->actingAs($purchaser);
        $this->post(route('improvement-decisions.apply', $stockProposal), [
            'confirmed' => '1',
            'application_note' => 'Supply confirms the forecast shortage and approves an objective without creating a purchase.',
            'product_id' => $product->id,
            'target_quantity' => 10,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('applied', $stockProposal->refresh()->status);
        $this->assertSame(1, ReplenishmentTarget::where('product_id', $product->id)->count());
        $this->actingAs($admin);

        $this->post(route('after-sales.quality.corrective-plans.proposals.store', $plan), [
            'type' => 'renewal', 'title' => 'Prepare a governed renewal review',
            'rationale' => 'The verified service improvement should be considered in the upcoming renewal.',
            'proposed_change' => 'Prepare a renewal opportunity for human commercial review without contacting the customer.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $renewalProposal = ServiceCorrectiveProposal::where('type', 'renewal')->sole();
        $this->patch(route('after-sales.quality.corrective-proposals.decision', $renewalProposal), [
            'action' => 'approve', 'note' => 'CRM approves preparation of an internal renewal opportunity for review.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('improvement-decisions.apply', $renewalProposal), [
            'confirmed' => '1',
            'application_note' => 'Prepare the internal opportunity now; customer contact remains a later human action.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('applied', $renewalProposal->refresh()->status);
        $this->assertNotNull($plan->maintenancePlan->refresh()->renewal_opportunity_id);
        $this->get(route('improvement-decisions.index', ['status' => 'applied']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('proposals.data', 4)
                ->where('summary.applied', 4));

        $this->patch(route('after-sales.quality.corrective-plans.transition', $plan), [
            'action' => 'start_verification', 'note' => 'The corrective action is complete and the next comparable cycle will be observed.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('after-sales.quality.corrective-plans.transition', $plan), [
            'action' => 'verify_effective', 'note' => 'This must wait for a completed follow-up cycle.',
        ])->assertSessionHasErrors('action');

        $followup = $commitment->replicate(['service_ticket_id', 'work_order_id']);
        $followup->period_starts_on = today()->addMonths(3);
        $followup->period_ends_on = today()->addMonths(4);
        $followup->completed_at = now()->addMonths(4);
        $followup->approved_at = now()->addMonths(3);
        $followup->completion_snapshot = ['time' => ['minutes' => 60, 'travel_km' => 5], 'materials' => ['actual_cost' => 10], 'evidence' => ['items' => [], 'published_documents' => 1, 'pending_documents' => 0]];
        $followup->save();

        $this->patch(route('after-sales.quality.corrective-plans.transition', $plan), [
            'action' => 'verify_effective', 'note' => 'The next comparable cycle completed without confirmed duration or travel deviations.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $plan->refresh();
        $this->assertSame('effective', $plan->status);
        $this->assertSame('effective', $plan->effectiveness_result);
        $this->assertEquals(0.0, $plan->effectiveness_snapshot['after']['deviation_rate']);
        $this->assertEquals(0.0, $plan->effectiveness_snapshot['volume_change_percent']);

        $this->get(route('after-sales.quality.index'))->assertInertia(fn (Assert $page) => $page
            ->has('correctivePlans', 1)
            ->where('correctivePlans.0.code', $plan->code)
            ->where('correctivePlans.0.effectiveness_result', 'effective')
            ->where('summary.pending_improvement_decisions', 0)
            ->where('summary.conflicting_improvement_proposals', 0));
    }

    /** @return array{User, User, ServiceCycleCommitment, InstallationAsset} */
    private function completedSecurityCycle(): array
    {
        $this->travelTo('2026-08-29 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $customer = Person::create(['category' => 'customer', 'company_name' => 'Quality Security Customer', 'first_name' => 'Quality']);
        $template = WorkOrderTemplate::create([
            'name' => 'Security recurring quality template', 'service_type' => 'security',
            'kind' => 'maintenance', 'title' => 'Security review', 'estimated_duration_minutes' => 60,
            'checklist' => [['label' => 'Inspect the security panel', 'is_required' => true]],
            'required_skills' => [], 'required_evidence' => [], 'handover_documents' => [],
            'allowed_transitions' => [], 'is_active' => true, 'is_system' => false,
        ]);
        $installation = Installation::create([
            'person_id' => $customer->id,
            'technician_user_id' => $technician->id,
            'service_type' => 'security',
            'status' => 'completed',
            'project_reference' => 'SEG-QUALITY-1',
            'site_address_line_1' => 'Calle Calidad 1',
        ]);
        $asset = InstallationAsset::create([
            'installation_id' => $installation->id,
            'asset_tag' => 'PANEL-QUALITY-1',
            'name' => 'Security panel',
            'category' => 'security_panel',
            'status' => 'active',
        ]);
        $plan = MaintenancePlan::create([
            'installation_id' => $installation->id,
            'work_order_template_id' => $template->id,
            'assigned_user_id' => $technician->id,
            'name' => 'Security quality care',
            'version' => 1,
            'service_level' => 'critical',
            'coverage_type' => 'preventive',
            'interval_months' => 3,
            'starts_on' => today(),
            'contract_ends_on' => today()->addYear(),
            'next_due_on' => today()->addMonths(3),
            'is_active' => true,
        ]);
        $product = Product::create([
            'type' => 'product', 'sku' => 'SEC-PREV-1', 'name' => 'Security preventive battery',
            'unit_id' => Unit::firstOrFail()->id,
            'tax_rate_id' => TaxRate::firstOrFail()->id,
            'sale_price' => 25, 'cost_price' => 10, 'track_stock' => true,
            'track_serials' => false, 'is_active' => true,
        ]);
        $plan->preventiveMaterials()->create([
            'product_id' => $product->id, 'quantity_per_visit' => 1,
            'expected_usage_percent' => 100, 'minimum_stock' => 2,
        ]);
        $plan->coveredAssets()->attach($asset);
        $order = WorkOrder::create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'number' => 'WO-QUALITY-1',
            'title' => 'Security review',
            'kind' => 'maintenance',
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $ticket = ServiceTicket::create([
            'installation_id' => $installation->id,
            'maintenance_plan_id' => $plan->id,
            'work_order_id' => $order->id,
            'number' => 'SAT-QUALITY-1',
            'kind' => 'maintenance',
            'priority' => 'normal',
            'status' => 'resolved',
            'title' => 'Security review',
            'reopened_count' => 1,
        ]);
        $commitment = ServiceCycleCommitment::create([
            'maintenance_plan_id' => $plan->id,
            'maintenance_plan_version' => 1,
            'period_starts_on' => today()->subMonth(),
            'period_ends_on' => today(),
            'status' => 'completed',
            'planned_visits' => 1,
            'planned_minutes' => 60,
            'planned_travel_km' => 5,
            'planned_revenue' => 200,
            'planned_labor_cost' => 50,
            'planned_material_cost' => 10,
            'planned_travel_cost' => 2.5,
            'capacity_snapshot' => [],
            'technical_snapshot' => [
                'plan' => ['covered_asset_ids' => [$asset->id]],
                'template' => [
                    'required_evidence' => [['key' => 'panel', 'required' => true]],
                    'handover_documents' => [['key' => 'certificate', 'required' => true]],
                ],
            ],
            'service_ticket_id' => $ticket->id,
            'work_order_id' => $order->id,
            'schedule_status' => 'confirmed',
            'completion_snapshot' => [
                'time' => ['minutes' => 120, 'travel_km' => 20],
                'materials' => ['actual_cost' => 50],
                'evidence' => ['items' => [], 'published_documents' => 0, 'pending_documents' => 1],
            ],
            'approval_note' => 'Approved service quality baseline.',
            'approved_by' => $admin->id,
            'approved_at' => now()->subMonth(),
            'completed_by' => $technician->id,
            'completed_at' => now(),
        ]);

        return [$admin, $technician, $commitment, $asset];
    }
}
