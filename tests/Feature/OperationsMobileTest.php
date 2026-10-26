<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationsMobileTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $tech, array $fields = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'installation_id' => Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Ubicación sintética'])->id,
            'assigned_user_id' => $tech->id, 'number' => 'OT-QA-'.str()->uuid(), 'title' => 'Panel móvil', 'kind' => 'maintenance', 'status' => 'planned',
            'scheduled_start' => '2026-10-26 09:00', 'scheduled_end' => '2026-10-26 10:00',
        ], $fields));
    }

    public function test_mobile_filters_and_detail_never_escape_technician_scope(): void
    {
        $tech = User::factory()->create(['role' => 'technician']);
        $other = User::factory()->create(['role' => 'technician']);
        $mine = $this->order($tech);
        $foreign = $this->order($other);
        $this->order($tech, ['scheduled_start' => '2026-10-27 09:00', 'scheduled_end' => '2026-10-27 10:00']);
        $this->actingAs($tech)->get('/work-orders?mine=1&q=Panel&status=planned&date_from=2026-10-26&date_to=2026-10-26')->assertInertia(fn (Assert $p) => $p->where('orders.total', 1)->where('orders.data.0.id', $mine->id));
        $this->get('/work-orders/'.$foreign->id.'?tab=reports')->assertForbidden();
        $this->patch('/work-orders/'.$foreign->id, ['status' => 'in_progress'])->assertForbidden();
    }

    public function test_timer_projection_only_contains_the_authenticated_users_open_entry(): void
    {
        $tech = User::factory()->create(['role' => 'technician']);
        $other = User::factory()->create(['role' => 'technician']);
        $order = $this->order($tech);
        $entry = TimeEntry::create(['work_order_id' => $order->id, 'user_id' => $tech->id, 'started_at' => now(), 'hourly_cost' => 25, 'mileage_rate' => 0]);
        TimeEntry::create(['work_order_id' => $order->id, 'user_id' => $other->id, 'started_at' => now(), 'hourly_cost' => 90, 'mileage_rate' => 0]);
        $this->actingAs($tech)->get('/work-orders/'.$order->id.'?tab=reports')->assertInertia(fn (Assert $p) => $p->where('runningTimer.id', $entry->id)->missing('runningTimer.cost')->missing('runningTimer.hourly_cost')->missing('runningTimer.user_id'));
        $this->patch('/time-entries/'.$entry->id.'/stop', ['notes' => 'Actuación sintética', 'travel_km' => 2])->assertRedirect();
        $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'notes' => 'Actuación sintética']);
        $this->get('/work-orders/'.$order->id.'?tab=reports')->assertInertia(fn (Assert $p) => $p->where('runningTimer', null));
    }

    public function test_existing_upload_contract_keeps_evidence_private_and_denies_other_orders(): void
    {
        Storage::fake(config('filesystems.private'));
        $tech = User::factory()->create(['role' => 'technician']);
        $mine = $this->order($tech);
        $foreign = $this->order(User::factory()->create(['role' => 'technician']));
        $this->actingAs($tech)->post('/work-orders/'.$mine->id.'/attachments', ['files' => []])->assertSessionHasErrors('files');
        $this->post('/work-orders/'.$foreign->id.'/attachments', ['files' => [UploadedFile::fake()->image('evidence.jpg')]])->assertForbidden();
        $this->post('/work-orders/'.$mine->id.'/attachments', ['files' => [UploadedFile::fake()->image('evidence.jpg')], 'caption' => 'Evidencia sintética'])->assertRedirect();
        $this->assertDatabaseHas('work_order_attachments', ['work_order_id' => $mine->id, 'caption' => 'Evidencia sintética', 'customer_visible' => false]);
    }
}
