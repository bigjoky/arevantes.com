<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsMobileAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_and_exports_require_authentication(): void
    {
        $this->get('/analytics')->assertRedirect('/login');
        $this->get('/analytics/export/pdf')->assertRedirect('/login');
        $this->get('/supply-analytics')->assertRedirect('/login');
    }

    public function test_export_preserves_custom_period_and_rejects_unavailable_formats(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $filters = ['area' => 'finance', 'period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-03-31'];
        $this->actingAs($user)->get(route('analytics.export', ['format' => 'pdf', ...$filters]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="analytics-finance-2026-01-01-2026-03-31.pdf"');
        $this->get(route('analytics.export', ['format' => 'csv', ...$filters]))->assertNotFound();
    }

    public function test_sales_user_cannot_export_finance_area_or_purchase_analytics(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user)->get(route('analytics.export', ['format' => 'pdf', 'area' => 'finance']))
            ->assertSessionHasErrors('area');
        $technician = User::factory()->create(['role' => 'technician']);
        $this->actingAs($technician)->get('/supply-analytics')->assertForbidden();
    }
}
