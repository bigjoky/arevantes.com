<?php

namespace Tests\Feature;

use App\Models\FieldServiceProfile;
use App\Models\Installation;
use App\Models\InstallationAsset;
use App\Models\MaintenancePlan;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\AfterSales\ServiceWorkGenerator;
use App\Services\Work\TechnicianSchedule;
use App\Services\Work\WorkOrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FieldServiceOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_checks_skills_zone_and_daily_capacity_before_planning(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create([
            'user_id' => $technician->id,
            'daily_capacity_minutes' => 120,
            'service_zones' => ['28001'],
            'skills' => ['solar'],
            'is_available' => true,
        ]);

        $this->expectException(ValidationException::class);
        app(TechnicianSchedule::class)->assertAvailable(
            $technician->id,
            '2026-08-10 08:00:00',
            '2026-08-10 11:00:00',
            null,
            ['ev_charger'],
            '08001',
        );
    }

    public function test_bulk_rescheduling_requires_a_reason_and_keeps_an_audit_trail(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $installation = $this->installation();
        $order = app(WorkOrderFactory::class)->create([
            'installation_id' => $installation->id,
            'title' => 'Commissioning',
            'kind' => 'commissioning',
            'scheduled_start' => '2026-08-10 08:00:00',
            'scheduled_end' => '2026-08-10 10:00:00',
        ], $commercial->id);

        $this->actingAs($commercial)->patch(route('work-orders.bulk-reschedule'), [
            'reason' => 'Customer requested a new date',
            'items' => [[
                'work_order_id' => $order->id,
                'scheduled_start' => '2026-08-12 08:00:00',
                'scheduled_end' => '2026-08-12 10:00:00',
            ]],
        ])->assertRedirect();

        $this->assertSame('2026-08-12', $order->refresh()->scheduled_start->toDateString());
        $this->assertDatabaseHas('work_order_reschedules', [
            'work_order_id' => $order->id,
            'reason' => 'Customer requested a new date',
        ]);
    }

    public function test_assets_keep_the_complete_replacement_trace(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $installation = $this->installation();
        $asset = InstallationAsset::create([
            'installation_id' => $installation->id,
            'asset_tag' => 'AST-OLD',
            'name' => 'Main inverter',
            'category' => 'inverter',
            'serial_number' => 'OLD-001',
            'status' => 'active',
        ]);

        $this->actingAs($commercial)->post(route('installations.assets.replace', [$installation, $asset]), [
            'asset_tag' => 'AST-NEW',
            'name' => 'Main inverter',
            'category' => 'inverter',
            'serial_number' => 'NEW-001',
            'status' => 'active',
            'commissioned_at' => '2026-08-06 12:00:00',
        ])->assertRedirect();

        $replacement = InstallationAsset::where('asset_tag', 'AST-NEW')->firstOrFail();
        $this->assertSame('replaced', $asset->refresh()->status);
        $this->assertSame($replacement->id, $asset->replaced_by_asset_id);
        $this->assertTrue($asset->events()->where('type', 'replacement')->exists());
        $this->assertTrue($replacement->events()->where('type', 'commissioned')->exists());
    }

    public function test_due_maintenance_generates_the_ticket_and_required_work_order(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $installation = $this->installation();
        $plan = MaintenancePlan::create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'name' => 'Quarterly inspection',
            'interval_months' => 3,
            'lead_days' => 14,
            'starts_on' => today(),
            'next_due_on' => today()->addDays(7),
            'is_active' => true,
            'generate_work_order' => true,
        ]);

        $this->assertSame(1, app(ServiceWorkGenerator::class)->generateDueMaintenance());
        $ticket = ServiceTicket::where('maintenance_plan_id', $plan->id)->firstOrFail();
        $order = WorkOrder::findOrFail($ticket->work_order_id);
        $this->assertSame('maintenance', $order->kind);
        $this->assertSame($technician->id, $order->assigned_user_id);
        $this->assertGreaterThan(0, $order->checklistItems()->where('is_required', true)->count());
    }

    private function installation(): Installation
    {
        return Installation::create([
            'service_type' => 'solar',
            'status' => 'completed',
            'site_address_line_1' => 'Calle Técnica 1',
            'site_postal_code' => '28001',
        ]);
    }
}
