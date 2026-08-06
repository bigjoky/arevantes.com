<?php

namespace Tests\Feature;

use App\Models\AnalyticsAlert;
use App\Models\FinancialBudget;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExecutiveAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_kpi_has_a_documented_formula_owner_source_and_frequency(): void
    {
        $metrics = config('analytics.metrics');

        $this->assertNotEmpty($metrics);
        foreach ($metrics as $key => $metric) {
            $this->assertNotEmpty($metric['formula'], "{$key} has no formula");
            $this->assertNotEmpty($metric['owner'], "{$key} has no owner");
            $this->assertNotEmpty($metric['source'], "{$key} has no source");
            $this->assertNotEmpty($metric['frequency'], "{$key} has no frequency");
        }
    }

    public function test_totals_are_reconciled_with_operational_records_and_explainable(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $project = Installation::create([
            'commercial_user_id' => $user->id,
            'service_type' => 'solar',
            'status' => 'completed',
            'project_reference' => 'PRJ-AN-1',
            'site_address_line_1' => 'Analytics street',
            'completed_date' => today(),
        ]);
        SalesDocument::create([
            'type' => 'invoice', 'installation_id' => $project->id, 'created_by' => $user->id,
            'number' => 'INV-AN-1', 'status' => 'issued', 'issue_date' => today(), 'due_date' => today()->addMonth(),
            'subtotal' => 1000, 'discount_amount' => 0, 'tax_amount' => 210, 'amount' => 1210, 'paid_amount' => 210,
            'customer_snapshot' => [],
        ]);
        SalesDocument::create([
            'type' => 'credit_note', 'installation_id' => $project->id, 'created_by' => $user->id,
            'number' => 'CN-AN-1', 'status' => 'issued', 'issue_date' => today(),
            'subtotal' => -100, 'discount_amount' => 0, 'tax_amount' => -21, 'amount' => -121, 'paid_amount' => 0,
            'customer_snapshot' => [],
        ]);
        $supplier = Supplier::create(['legal_name' => 'Analytics Supply SL', 'country' => 'ES', 'is_active' => true]);
        PurchaseDocument::create([
            'type' => 'supplier_invoice', 'installation_id' => $project->id, 'supplier_id' => $supplier->id, 'created_by' => $user->id,
            'number' => 'PINV-AN-1', 'status' => 'registered', 'issue_date' => today(),
            'subtotal' => 400, 'tax_amount' => 84, 'amount' => 484, 'paid_amount' => 0,
        ]);
        Opportunity::create([
            'installation_id' => $project->id, 'owner_user_id' => $user->id, 'created_by' => $user->id,
            'code' => 'OPP-AN-1', 'title' => 'Analytics opportunity', 'stage' => 'qualified',
            'probability' => 50, 'estimated_value' => 5000, 'expected_close_on' => today(),
        ]);
        WorkOrder::create([
            'installation_id' => $project->id, 'created_by' => $user->id, 'number' => 'WO-AN-1',
            'title' => 'Analytics work', 'kind' => 'installation', 'status' => 'completed', 'scheduled_start' => now(),
        ]);
        FinancialBudget::create(['year' => today()->year, 'month' => today()->month, 'category' => 'revenue', 'amount' => 1500, 'created_by' => $user->id]);

        $this->actingAs($user)->get(route('analytics.index', ['area' => 'executive', 'period' => 'month']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('analytics/index')
                ->where('metrics.0.key', 'net_revenue')
                ->where('metrics.0.value', 1089)
                ->where('metrics.0.target', 1500)
                ->where('metrics.1.key', 'open_pipeline')
                ->where('metrics.1.value', 5000)
                ->where('metrics.2.key', 'completed_projects')
                ->where('metrics.2.value', 1)
                ->where('metrics.3.key', 'purchase_spend')
                ->where('metrics.3.value', 484)
                ->where('metrics.4.key', 'outstanding_receivables')
                ->where('metrics.4.value', 1000)
                ->where('metrics.5.key', 'gross_margin')
                ->where('metrics.5.value', 605));

        $this->get(route('analytics.index', ['area' => 'executive', 'period' => 'month', 'metric' => 'net_revenue']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedMetric.value', 1089)
                ->has('records', 2)
                ->where('records.0.url', route('sales-documents.show', 2))
                ->where('records.1.url', route('sales-documents.show', 1)));
    }

    public function test_technician_only_sees_operations_and_assigned_scope(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $other = User::factory()->create(['role' => 'technician']);
        $ownProject = Installation::create(['technician_user_id' => $technician->id, 'service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Own']);
        $otherProject = Installation::create(['technician_user_id' => $other->id, 'service_type' => 'solar', 'status' => 'scheduled', 'site_address_line_1' => 'Other']);
        WorkOrder::create(['installation_id' => $ownProject->id, 'assigned_user_id' => $technician->id, 'created_by' => $technician->id, 'number' => 'WO-OWN', 'title' => 'Own', 'kind' => 'installation', 'status' => 'completed', 'scheduled_start' => now()]);
        WorkOrder::create(['installation_id' => $otherProject->id, 'assigned_user_id' => $other->id, 'created_by' => $other->id, 'number' => 'WO-OTHER', 'title' => 'Other', 'kind' => 'installation', 'status' => 'pending', 'scheduled_start' => now()]);

        $this->actingAs($technician)->get(route('analytics.index', ['period' => 'month']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('areas', 1)
                ->where('areas.0.key', 'operations')
                ->where('metrics.1.key', 'work_order_completion')
                ->where('metrics.1.value', 100)
                ->has('projects', 1));

        $this->get(route('analytics.index', ['area' => 'finance']))->assertSessionHasErrors('area');
    }

    public function test_alerts_subscriptions_and_exports_respect_the_current_user(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $other = User::factory()->create(['role' => 'commercial']);

        $this->actingAs($user)->post(route('analytics.alerts.store'), [
            'area' => 'finance', 'metric_key' => 'outstanding_receivables', 'operator' => '>', 'threshold' => 10000,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $alert = AnalyticsAlert::firstOrFail();
        $this->assertSame($user->id, $alert->user_id);
        $this->actingAs($other)->delete(route('analytics.alerts.destroy', $alert))->assertForbidden();

        $this->actingAs($user)->post(route('analytics.subscriptions.store'), [
            'area' => 'finance', 'frequency' => 'weekly', 'format' => 'pdf',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('analytics_report_subscriptions', ['user_id' => $user->id, 'area' => 'finance']);

        $this->get(route('analytics.export', ['format' => 'excel', 'area' => 'finance', 'period' => 'month']))
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
        $this->get(route('analytics.export', ['format' => 'pdf', 'area' => 'finance', 'period' => 'month']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
