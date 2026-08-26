<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\Opportunity;
use App\Models\ServiceTicket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Ai\RelevantBusinessContext;
use App\Services\Analytics\AnalyticsFilter;
use App\Services\Analytics\RecurringServiceHealth;
use App\Services\Planning\RecurringServiceCapacityForecast;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RecurringServiceIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_health_reconciles_contracts_sla_renewals_and_authorized_costs(): void
    {
        $this->travelTo('2026-08-26 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $solar = Installation::create(['person_id' => null, 'technician_user_id' => $technician->id, 'service_type' => 'solar', 'status' => 'completed', 'project_reference' => 'FV-HEALTH', 'site_address_line_1' => 'Calle Solar 1']);
        $security = Installation::create(['service_type' => 'security', 'status' => 'completed', 'project_reference' => 'SEG-HEALTH', 'site_address_line_1' => 'Calle Seguridad 1']);
        $plan = MaintenancePlan::create(['installation_id' => $solar->id, 'assigned_user_id' => $technician->id, 'name' => 'Solar care', 'service_level' => 'priority', 'coverage_type' => 'full', 'interval_months' => 3, 'starts_on' => today(), 'contract_ends_on' => '2026-12-31', 'billing_frequency_months' => 1, 'billing_amount' => 100, 'next_due_on' => today()->addWeek(), 'is_active' => true]);
        MaintenancePlan::create(['installation_id' => $security->id, 'name' => 'Security care', 'service_level' => 'critical', 'coverage_type' => 'preventive', 'interval_months' => 6, 'starts_on' => today(), 'contract_ends_on' => '2026-11-30', 'next_due_on' => today()->addWeeks(2), 'is_active' => true]);
        $renewal = Opportunity::create(['installation_id' => $solar->id, 'owner_user_id' => $admin->id, 'created_by' => $admin->id, 'updated_by' => $admin->id, 'code' => 'OPP-RENEW-1', 'title' => 'Solar care renewal', 'commercial_kind' => 'renewal', 'renewal_due_on' => '2026-12-31', 'stage' => 'won', 'probability' => 100, 'estimated_value' => 1200, 'expected_close_on' => '2026-12-31', 'source' => 'recurring_customer']);
        $plan->update(['renewal_opportunity_id' => $renewal->id]);
        $order = WorkOrder::create(['installation_id' => $solar->id, 'assigned_user_id' => $technician->id, 'number' => 'WO-SVC-HEALTH', 'title' => 'Solar service', 'kind' => 'maintenance', 'status' => 'completed']);
        ServiceTicket::create(['installation_id' => $solar->id, 'maintenance_plan_id' => $plan->id, 'work_order_id' => $order->id, 'number' => 'SAT-SVC-HEALTH', 'kind' => 'maintenance', 'priority' => 'normal', 'status' => 'resolved', 'title' => 'Solar service', 'first_response_due_at' => now()->addHour(), 'first_responded_at' => now()->addMinutes(20), 'resolution_due_at' => now()->addHours(4), 'resolved_at' => now()->addHours(2), 'reopened_count' => 0]);
        TimeEntry::create(['work_order_id' => $order->id, 'user_id' => $technician->id, 'started_at' => now(), 'ended_at' => now()->addHour(), 'duration_minutes' => 60, 'hourly_cost' => 50, 'travel_km' => 10, 'mileage_rate' => 0.5]);

        $filter = new AnalyticsFilter(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31')->endOfDay(), 'year', null, null, 'executive', $admin);
        $health = app(RecurringServiceHealth::class)->build($filter, true);

        $this->assertSame(2, $health['summary']['installed_base']);
        $this->assertSame(2, $health['summary']['active_contracts']);
        $this->assertSame(100.0, $health['summary']['attach_rate']);
        $this->assertSame(1200.0, $health['summary']['annual_recurring_value']);
        $this->assertSame(2, $health['summary']['renewals_due']);
        $this->assertSame(1, $health['summary']['renewals_prepared']);
        $this->assertSame(100.0, $health['summary']['renewal_conversion']);
        $this->assertSame(100.0, $health['summary']['response_sla_attainment']);
        $this->assertSame(100.0, $health['summary']['resolution_sla_attainment']);
        $this->assertSame(100.0, $health['summary']['first_time_fix_rate']);
        $this->assertSame(55.0, $health['summary']['actual_service_cost']);
        $this->assertNull(app(RecurringServiceHealth::class)->build($filter, false)['summary']['actual_service_cost']);
        $this->actingAs($admin)->get(route('analytics.index', ['area' => 'executive', 'period' => 'year']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('recurringHealth.summary.active_contracts', 2)
            ->where('recurringHealth.summary.attach_rate', 100)
            ->where('recurringHealth.summary.actual_service_cost', 55));
    }

    public function test_capacity_and_arevia_expose_only_non_committing_recurring_service_intelligence(): void
    {
        $this->travelTo('2026-08-26 10:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $installation = Installation::create(['technician_user_id' => $technician->id, 'service_type' => 'security', 'status' => 'completed', 'project_reference' => 'SEG-CAP', 'site_address_line_1' => 'Calle Capacidad 1']);
        MaintenancePlan::create(['installation_id' => $installation->id, 'name' => 'Critical alarm care', 'service_level' => 'critical', 'coverage_type' => 'full', 'interval_months' => 3, 'starts_on' => today(), 'next_due_on' => today()->addWeek(), 'is_active' => true]);

        $capacity = app(RecurringServiceCapacityForecast::class)->forecast(today(), 6, $admin);
        $this->assertSame(1, $capacity['summary']['due_plans']);
        $this->assertSame(1, $capacity['summary']['unassigned_plans']);
        $this->assertSame(1, $capacity['summary']['critical_unassigned']);
        $this->assertSame(90, $capacity['summary']['uncommitted_service_minutes']);
        $this->assertSame(0, WorkOrder::count());

        $context = app(RelevantBusinessContext::class)->search($admin, 'Revisa mantenimiento, renovación y repuestos', '/after-sales');
        $this->assertStringContainsString('[CRM-SVC-1]', $context);
        $this->assertStringContainsString('Critical alarm care', $context);
        $this->assertStringContainsString('[SUP-SVC-1]', $context);
        $this->assertStringContainsString('[GOV-SVC-1]', $context);
        $this->assertStringContainsString('has not reserved stock or created purchase documents', $context);
        $this->assertSame(0, WorkOrder::count());
        $this->actingAs($admin)->get(route('after-sales.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('serviceCapacity.summary.due_plans', 1)
                ->where('serviceCapacity.summary.critical_unassigned', 1));
    }
}
