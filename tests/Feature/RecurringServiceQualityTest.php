<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\InstallationAsset;
use App\Models\MaintenancePlan;
use App\Models\ServiceCycleCommitment;
use App\Models\ServiceCycleDeviation;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\WorkOrder;
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

    /** @return array{User, User, ServiceCycleCommitment, InstallationAsset} */
    private function completedSecurityCycle(): array
    {
        $this->travelTo('2026-08-29 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create([
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
            'assigned_user_id' => $technician->id,
            'name' => 'Security quality care',
            'version' => 1,
            'service_level' => 'critical',
            'coverage_type' => 'preventive',
            'interval_months' => 3,
            'starts_on' => today(),
            'next_due_on' => today()->addMonths(3),
            'is_active' => true,
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
