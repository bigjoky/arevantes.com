<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationsRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $fields = []): WorkOrder
    {
        return WorkOrder::create([...['installation_id' => Installation::create(['service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Lugar de ejecución'])->id, 'number' => 'WO-test-'.str()->uuid(), 'title' => 'Trabajo de prueba', 'kind' => 'maintenance', 'status' => 'planned'], ...$fields]);
    }

    public function test_dashboard_counts_execution_dates_and_completion_timestamp(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 7)->setTime(12, 0));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->order(['scheduled_start' => now()->setTime(9, 0), 'scheduled_end' => now()->setTime(18, 0)]);
        $this->order(['status' => 'in_progress', 'scheduled_start' => now()->subDay(), 'scheduled_end' => now()->subHour()]);
        $this->order(['status' => 'completed', 'completed_at' => now()->subDay()]);
        $this->order(['status' => 'completed', 'completed_at' => now()->subMonth()]);
        $this->get('/operations')->assertOk()->assertInertia(fn (Assert $p) => $p->component('operations/dashboard')->where('metrics.today', 2)->where('metrics.pending', 2)->where('metrics.in_progress', 1)->where('metrics.delayed', 2)->where('metrics.completed', 1));
    }

    public function test_list_is_paginated_and_filters_real_status_and_owner(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        for ($i = 0; $i < 18; $i++) {
            $this->order(['assigned_user_id' => $user->id]);
        }
        $this->order(['status' => 'cancelled']);
        $this->get('/work-orders?status=planned&mine=1&per_page=10')->assertInertia(fn (Assert $p) => $p->component('work-orders/list')->has('orders.data', 10)->where('orders.total', 18));
        $this->get('/work-orders?kind=invalid')->assertRedirect();
    }

    public function test_technician_scope_applies_to_every_operations_projection(): void
    {
        $tech = User::factory()->create(['role' => 'technician']);
        $mine = $this->order(['assigned_user_id' => $tech->id]);
        $other = $this->order();
        $this->actingAs($tech)->get('/work-orders')->assertInertia(fn (Assert $p) => $p->where('orders.total', 1));
        $this->get('/work-orders/'.$other->id)->assertForbidden();
        $this->get('/work-orders/'.$mine->id)->assertInertia(fn (Assert $p) => $p->component('work-orders/show')->where('order.id', $mine->id)->where('content', null));
        $this->get('/operations')->assertInertia(fn (Assert $p) => $p->where('metrics.pending', 1));
        $this->get('/operations/planning')->assertInertia(fn (Assert $p) => $p->where('backlog.total', 1));
    }

    public function test_planning_bounds_the_week_and_agenda_deduplicates_linked_activities(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $inside = $this->order(['scheduled_start' => '2026-10-07 09:00', 'scheduled_end' => '2026-10-07 11:00']);
        $this->order(['scheduled_start' => '2026-11-07 09:00', 'scheduled_end' => '2026-11-07 11:00']);
        CrmActivity::create(['work_order_id' => $inside->id, 'title' => 'Linked', 'type' => 'task', 'status' => 'pending', 'starts_at' => '2026-10-07 09:00']);
        CrmActivity::create(['title' => 'Visita independiente', 'type' => 'visit', 'status' => 'pending', 'starts_at' => '2026-10-08 09:00']);
        $this->get('/operations/planning?week=2026-10-07')->assertInertia(fn (Assert $p) => $p->has('orders', 1)->has('days', 7)->where('range.start', '2026-10-05'));
        $this->get('/operations/agenda?week=2026-10-07')->assertInertia(fn (Assert $p) => $p->has('orders', 1)->has('activities', 1)->where('activities.0.title', 'Visita independiente'));
    }

    public function test_detail_lazy_tabs_and_checklist_progress(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order();
        $task = $order->checklistItems()->create(['position' => 1, 'label' => 'Inspeccionar', 'is_required' => true]);
        $this->get('/work-orders/'.$order->id.'?tab=tasks')->assertInertia(fn (Assert $p) => $p->has('content.data', 1)->where('order.completed_tasks_count', 0));
        $this->patch('/work-orders/'.$order->id.'/checklist/'.$task->id, ['completed' => true])->assertRedirect();
        $this->get('/work-orders/'.$order->id)->assertInertia(fn (Assert $p) => $p->where('content', null)->where('order.completed_tasks_count', 1));
        foreach (['materials', 'documents', 'photos', 'reports', 'activity'] as $tab) {
            $this->get('/work-orders/'.$order->id.'?tab='.$tab)->assertOk();
        }
    }

    public function test_reprogramming_rejects_conflicts_and_stale_versions_without_writes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $one = $this->order(['assigned_user_id' => $admin->id, 'scheduled_start' => '2026-10-07 09:00', 'scheduled_end' => '2026-10-07 11:00']);
        $two = $this->order();
        $data = ['assigned_user_id' => $admin->id, 'scheduled_start' => '2026-10-07 10:00', 'scheduled_end' => '2026-10-07 12:00', 'reschedule_reason' => 'Reorganización'];
        $this->patch('/work-orders/'.$two->id.'/schedule', $data)->assertSessionHasErrors('scheduled_start');
        $data['scheduled_start'] = '2026-10-07 13:00';
        $data['scheduled_end'] = '2026-10-07 14:00';
        $this->patch('/work-orders/'.$two->id.'/schedule', [...$data, 'expected_updated_at' => '2020-01-01'])->assertSessionHasErrors('expected_updated_at');
        $this->assertNull($two->refresh()->scheduled_start);
        $this->patch('/work-orders/'.$two->id.'/schedule', [...$data, 'expected_updated_at' => $two->updated_at->toISOString()])->assertRedirect();
        $this->assertSame($admin->id, $two->refresh()->assigned_user_id);
        $this->assertSame(1, $two->reschedules()->count());
    }

    public function test_legacy_deep_link_redirects_to_full_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order();
        $this->get('/work-orders?work_order='.$order->id)->assertRedirect('/work-orders/'.$order->id);
    }

    public function test_multiple_execution_orders_preserve_installation_hierarchy_and_audit_completion(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $first = $this->order(['kind' => 'installation', 'signed_at' => now()]);
        $second = $this->order(['installation_id' => $first->installation_id, 'kind' => 'commissioning', 'status' => 'in_progress']);
        $first->attachments()->create(['kind' => 'photo', 'original_name' => 'completion.jpg', 'path' => 'test.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        $this->patch('/work-orders/'.$first->id, ['status' => 'completed', 'completion_notes' => 'Trabajo verificado'])->assertRedirect();
        $this->assertNotNull($first->refresh()->completed_at);
        $this->assertSame('in_progress', $first->installation->refresh()->status);
        $this->assertSame('in_progress', $second->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => WorkOrder::class, 'auditable_id' => $first->id, 'event' => 'updated']);
        $this->patch('/work-orders/'.$first->id, ['status' => 'planned'])->assertSessionHasErrors('status');
    }

    public function test_creation_preselects_installation_and_financial_data_is_not_disclosed_to_technicians(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $order = $this->order(['assigned_user_id' => $technician->id]);
        $order->timeEntries()->create(['user_id' => $technician->id, 'started_at' => now()->subHour(), 'ended_at' => now(), 'duration_minutes' => 60, 'hourly_cost' => 99, 'mileage_rate' => 1]);
        $this->actingAs($admin)->get('/work-orders/create?installation='.$order->installation_id)->assertInertia(fn (Assert $p) => $p->where('selectedInstallation', (string) $order->installation_id)->has('installations', 1));
        $this->actingAs($technician)->get('/work-orders/create')->assertForbidden();
        $this->get('/work-orders/'.$order->id.'?tab=reports')->assertInertia(fn (Assert $p) => $p->missing('content.data.0.cost')->missing('content.data.0.hourly_cost'));
        $this->get('/work-orders/manage?work_order='.$order->id)->assertInertia(fn (Assert $p) => $p->missing('selected.time_entries.0.cost')->missing('selected.time_entries.0.hourly_cost')->where('selected.material_plan.planned_cost', null)->where('selected.planning_readiness.rows', [])->where('canViewFinance', false));
    }

    public function test_agenda_day_month_and_timezone_navigation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/operations/agenda?view=day&week=2026-10-25')->assertInertia(fn (Assert $p) => $p->where('range.start', '2026-10-25')->where('range.end', '2026-10-25')->where('range.next', '2026-10-26')->where('timezone', config('app.timezone')));
        $this->get('/operations/agenda?view=month&week=2026-10-25')->assertInertia(fn (Assert $p) => $p->where('range.start', '2026-09-28')->where('range.end', '2026-11-01')->where('range.next', '2026-11-25'));
    }

    public function test_stale_workflow_and_completion_requirements_have_visible_web_errors(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->order();
        $this->patch('/work-orders/'.$order->id, ['status' => 'blocked', 'expected_updated_at' => '2020-01-01'])->assertSessionHasErrors('expected_updated_at');
        $this->assertSame('planned', $order->refresh()->status);
        $this->withHeader('X-Inertia', 'true')->patch('/work-orders/'.$order->id, ['status' => 'completed', 'completion_notes' => 'Inspección'])->assertSessionHasErrors('status');
        $this->assertNull($order->refresh()->completed_at);
    }
}
