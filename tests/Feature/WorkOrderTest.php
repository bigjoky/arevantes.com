<?php

namespace Tests\Feature;

use App\Models\FieldServiceProfile;
use App\Models\Installation;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use App\Services\Work\WorkOrderFactory;
use App\Services\Work\WorkOrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_business_line_has_an_operational_handover_template(): void
    {
        foreach (['solar', 'hvac', 'ev_charger', 'electrical', 'security'] as $serviceType) {
            $templates = WorkOrderTemplate::query()
                ->where('service_type', $serviceType)
                ->where('is_system', true)
                ->whereIn('kind', ['installation', 'commissioning'])
                ->get();

            $this->assertCount(2, $templates, "{$serviceType} must have installation and commissioning templates.");
            $this->assertTrue($templates->every(fn (WorkOrderTemplate $template) => count($template->handover_documents ?? []) >= 4), "{$serviceType} templates must define a complete handover package.");
        }
    }

    public function test_a_technician_can_track_only_their_assigned_work_order(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'skills' => ['solar', 'electrical']]);
        $other = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Sol 1']);
        $this->actingAs($commercial)->post(route('work-orders.store'), ['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'title' => 'Install inverter'])->assertRedirect();
        $order = WorkOrder::firstOrFail();
        // PostgreSQL sequences are not rolled back between transactional tests.
        $this->assertMatchesRegularExpression('/^WO-'.now()->format('Y').'-[0-9]{5,}$/', $order->number);
        $this->assertGreaterThanOrEqual(5, $order->checklistItems()->count());

        $this->actingAs($other)->post(route('work-orders.timer.start', $order))->assertForbidden();
        $this->actingAs($technician)->post(route('work-orders.timer.start', $order))->assertRedirect();
        $this->assertSame('in_progress', $installation->refresh()->status);
        $entry = TimeEntry::firstOrFail();
        $this->actingAs($technician)->post(route('work-orders.timer.start', $order))->assertUnprocessable();

        $entry->update(['started_at' => now()->subMinutes(90), 'hourly_cost' => 20, 'mileage_rate' => 0.26]);
        $this->actingAs($technician)->patch(route('time-entries.stop', $entry), ['travel_km' => 10, 'notes' => 'Completed installation'])->assertRedirect();
        $entry->refresh();
        $this->assertSame(90, $entry->duration_minutes);
        $this->assertSame(32.6, $entry->cost);
        $this->assertSame('in_progress', $order->refresh()->status);
    }

    public function test_required_checklist_signature_and_private_evidence_control_completion(): void
    {
        Storage::fake('local');
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'skills' => ['solar', 'electrical']]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Calle Solar 3']);
        $this->actingAs($commercial)->post(route('work-orders.store'), ['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'title' => 'Solar commissioning'])->assertRedirect();
        $order = WorkOrder::firstOrFail();

        $this->patch(route('work-orders.update', $order), ['status' => 'completed'])->assertUnprocessable();
        $this->post(route('work-orders.attachments.store', $order), ['files' => [UploadedFile::fake()->image('finished.jpg')]])->assertRedirect();
        $attachment = $order->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->get(route('work-orders.attachments.download', [$order, $attachment]))->assertOk();
        $this->post(route('work-orders.attachments.store', $order), ['files' => [UploadedFile::fake()->image('before.jpg')], 'evidence_key' => 'before_work'])->assertRedirect();
        $this->post(route('work-orders.attachments.store', $order), ['files' => [UploadedFile::fake()->image('final.jpg')], 'evidence_key' => 'final_installation'])->assertRedirect();

        foreach ($order->checklistItems as $item) {
            $this->patch(route('work-orders.checklist.update', [$order, $item]), ['completed' => true])->assertRedirect();
        }
        $signature = 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\n".'test-signature');
        $this->post(route('work-orders.signature.store', $order), ['signed_by_name' => 'Ana Customer', 'signature' => $signature])->assertRedirect();
        $this->patch(route('work-orders.update', $order), ['status' => 'completed', 'completion_notes' => 'Accepted'])->assertRedirect();
        $this->assertSame('completed', $order->refresh()->status);
        $this->assertSame('completed', $installation->refresh()->status);
        $this->assertSame('Ana Customer', $order->signed_by_name);
        Storage::disk('local')->assertExists($order->signature_path);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $order->getMorphClass(),
            'auditable_id' => $order->id,
            'event' => 'updated',
        ]);
    }

    public function test_work_order_keeps_its_workflow_when_the_template_changes(): void
    {
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Calle Flujo 1']);
        $template = WorkOrderTemplate::create([
            'name' => 'Controlled workflow',
            'kind' => 'maintenance',
            'title' => 'Controlled maintenance',
            'estimated_duration_minutes' => 60,
            'required_skills' => [],
            'checklist' => [['label' => 'Inspect', 'is_required' => true]],
            'allowed_transitions' => [
                'planned' => ['blocked'],
                'blocked' => ['planned'],
                'in_progress' => [],
                'completed' => [],
                'cancelled' => [],
            ],
            'is_active' => true,
        ]);
        $order = app(WorkOrderFactory::class)->create([
            'installation_id' => $installation->id,
            'work_order_template_id' => $template->id,
            'title' => 'Snapshot workflow',
        ]);

        $template->update(['allowed_transitions' => [
            'planned' => ['in_progress'],
            'in_progress' => ['completed'],
            'blocked' => [],
            'completed' => [],
            'cancelled' => [],
        ]]);

        $this->assertSame(['blocked'], $order->refresh()->allowed_transitions_snapshot['planned']);
        $this->expectException(ValidationException::class);
        app(WorkOrderWorkflow::class)->assertTransition($order, 'in_progress');
    }

    public function test_manual_time_uses_historical_cost_rates(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'skills' => ['solar', 'electrical']]);
        $installation = Installation::create(['service_type' => 'ev_charger', 'status' => 'in_progress', 'site_address_line_1' => 'Calle Carga 2']);
        $order = WorkOrder::create(['installation_id' => $installation->id, 'assigned_user_id' => $technician->id, 'created_by' => $commercial->id, 'number' => 'WO-2026-00002', 'title' => 'Commission charger']);

        $this->actingAs($commercial)->post(route('work-orders.time-entries.store', $order), ['user_id' => $technician->id, 'started_at' => '2026-07-13 08:00', 'ended_at' => '2026-07-13 10:30', 'hourly_cost' => 24, 'travel_km' => 20, 'mileage_rate' => 0.30])->assertRedirect();
        $entry = TimeEntry::firstOrFail();
        $this->assertSame(150, $entry->duration_minutes);
        $this->assertSame(66.0, $entry->cost);
    }

    public function test_a_technician_can_edit_the_details_of_their_maintenance_order(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $other = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'completed', 'site_address_line_1' => 'Calle Mantenimiento 1']);
        $order = app(WorkOrderFactory::class)->create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'kind' => 'maintenance',
            'title' => 'Annual maintenance',
        ]);

        $this->actingAs($technician)->patch(route('work-orders.details.update', $order), [
            'title' => 'Annual inverter maintenance',
            'kind' => 'maintenance',
            'estimated_duration_minutes' => 120,
            'required_skills' => 'Electrical, Inverters, electrical',
            'service_zone' => 'Madrid centro',
            'instructions' => 'Check protections and update firmware.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('Annual inverter maintenance', $order->title);
        $this->assertSame(120, $order->estimated_duration_minutes);
        $this->assertSame(['Electrical', 'Inverters'], $order->required_skills);
        $this->assertSame('Madrid centro', $order->service_zone);
        $this->assertSame('Check protections and update firmware.', $order->instructions);
        $this->assertDatabaseHas('crm_activities', [
            'work_order_id' => $order->id,
            'title' => 'Annual inverter maintenance',
            'type' => 'visit',
        ]);

        $this->actingAs($other)->patch(route('work-orders.details.update', $order), [
            'title' => 'Unauthorized change',
            'kind' => 'maintenance',
        ])->assertForbidden();
        $this->assertSame('Annual inverter maintenance', $order->refresh()->title);
    }

    public function test_schedule_prevents_technician_overlaps_and_updates_the_agenda(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'skills' => ['solar', 'electrical']]);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Calle Solar 4']);
        $otherInstallation = Installation::create(['service_type' => 'solar', 'status' => 'approved', 'site_address_line_1' => 'Calle Solar 5']);
        $start = now()->addDays(2)->setTime(9, 0);

        $this->actingAs($commercial)->post(route('work-orders.store'), [
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'kind' => 'installation',
            'title' => 'Install panels',
            'scheduled_start' => $start->format('Y-m-d H:i:s'),
            'scheduled_end' => $start->copy()->addHours(4)->format('Y-m-d H:i:s'),
        ])->assertRedirect();
        $order = WorkOrder::firstOrFail();
        $this->assertSame('scheduled', $installation->refresh()->status);
        $this->assertDatabaseHas('crm_activities', ['work_order_id' => $order->id, 'assigned_user_id' => $technician->id, 'status' => 'pending']);

        $this->post(route('work-orders.store'), [
            'installation_id' => $otherInstallation->id,
            'assigned_user_id' => $technician->id,
            'kind' => 'installation',
            'title' => 'Conflicting installation',
            'scheduled_start' => $start->copy()->addHour()->format('Y-m-d H:i:s'),
            'scheduled_end' => $start->copy()->addHours(3)->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('scheduled_start');
        $this->assertSame(1, WorkOrder::count());

        $newStart = $start->copy()->addDay();
        $this->patch(route('work-orders.schedule.update', $order), [
            'assigned_user_id' => $technician->id,
            'kind' => 'installation',
            'title' => 'Install panels and inverter',
            'scheduled_start' => $newStart->format('Y-m-d H:i:s'),
            'scheduled_end' => $newStart->copy()->addHours(6)->format('Y-m-d H:i:s'),
            'instructions' => 'Bring lifting equipment.',
            'reschedule_reason' => 'Customer requested a later date.',
        ])->assertRedirect();

        $this->assertSame('Install panels and inverter', $order->refresh()->title);
        $activity = $order->activities()->firstOrFail();
        $this->assertTrue($activity->starts_at->equalTo($newStart));
        $this->assertSame('Bring lifting equipment.', $order->instructions);
        $this->assertDatabaseHas('work_order_reschedules', ['work_order_id' => $order->id, 'reason' => 'Customer requested a later date.']);
    }

    public function test_completed_survey_moves_the_installation_to_approved(): void
    {
        Storage::fake('local');
        $commercial = User::factory()->create(['role' => 'commercial']);
        $installation = Installation::create(['service_type' => 'solar', 'status' => 'survey_booked', 'site_address_line_1' => 'Calle Solar 6']);
        $this->actingAs($commercial)->post(route('work-orders.store'), [
            'installation_id' => $installation->id,
            'kind' => 'survey',
            'title' => 'Technical survey',
        ])->assertRedirect();
        $order = WorkOrder::firstOrFail();
        $order->checklistItems()->update(['completed_at' => now(), 'completed_by' => $commercial->id]);
        $order->update(['signed_by_name' => 'Ana Customer', 'signed_at' => now()]);
        foreach (['site_overview', 'existing_services'] as $evidenceKey) {
            $order->attachments()->create(['uploaded_by' => $commercial->id, 'kind' => 'photo', 'evidence_key' => $evidenceKey, 'original_name' => $evidenceKey.'.jpg', 'path' => 'work-orders/'.$evidenceKey.'.jpg', 'mime_type' => 'image/jpeg', 'size' => 100]);
        }

        $this->patch(route('work-orders.update', $order), ['status' => 'completed', 'completion_notes' => 'Survey completed and documented.'])->assertRedirect();

        $this->assertSame('approved', $installation->refresh()->status);
        $this->assertDatabaseHas('crm_activities', ['work_order_id' => $order->id, 'status' => 'completed']);
    }

    public function test_business_line_template_snapshots_and_enforces_typed_evidence(): void
    {
        $commercial = User::factory()->create(['role' => 'commercial']);
        $installation = Installation::create(['service_type' => 'electrical', 'status' => 'survey_booked', 'site_address_line_1' => 'Calle Luz 12']);

        $order = app(WorkOrderFactory::class)->create([
            'installation_id' => $installation->id,
            'kind' => 'survey',
        ], $commercial->id);

        $this->assertSame('Electrical · Technical survey', $order->template->name);
        $this->assertContains('electrical_survey', $order->required_skills);
        $this->assertCount(3, $order->required_evidence);
        $order->checklistItems()->update(['completed_at' => now(), 'completed_by' => $commercial->id]);
        $order->update(['signed_by_name' => 'Ana Customer', 'signed_at' => now()]);
        $order->attachments()->create(['uploaded_by' => $commercial->id, 'kind' => 'photo', 'original_name' => 'generic.jpg', 'path' => 'generic.jpg', 'mime_type' => 'image/jpeg', 'size' => 100]);

        $this->actingAs($commercial)->patch(route('work-orders.update', $order), ['status' => 'completed', 'completion_notes' => 'Survey documented.'])->assertUnprocessable();

        foreach ($order->required_evidence as $requirement) {
            $order->attachments()->create(['uploaded_by' => $commercial->id, 'kind' => 'photo', 'evidence_key' => $requirement['key'], 'original_name' => $requirement['key'].'.jpg', 'path' => $requirement['key'].'.jpg', 'mime_type' => 'image/jpeg', 'size' => 100]);
        }

        $this->patch(route('work-orders.update', $order), ['status' => 'completed', 'completion_notes' => 'Survey documented.'])->assertRedirect();
    }
}
