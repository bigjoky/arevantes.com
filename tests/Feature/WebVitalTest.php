<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebVitalSample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebVitalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_store_a_privacy_safe_web_vital_sample(): void
    {
        config(['performance.release' => 'deployment-123']);

        $response = $this->postJson(route('performance.web-vitals.store'), [
            'route_name' => 'dashboard',
            'metric' => 'LCP',
            'rating' => 'poor',
            'value' => 1800.25,
            'delta' => 125.5,
            'device' => 'mobile',
            'navigation_type' => 'navigate',
        ])->assertNoContent();

        $this->assertDatabaseHas('web_vital_samples', [
            'route_name' => 'dashboard',
            'metric' => 'LCP',
            'rating' => 'good',
            'device' => 'mobile',
            'release' => 'deployment-123',
        ]);
        $this->assertFalse(Schema::hasColumn('web_vital_samples', 'url'));
        $this->assertFalse(Schema::hasColumn('web_vital_samples', 'user_id'));
        $this->assertFalse(Schema::hasColumn('web_vital_samples', 'ip_address'));
    }

    public function test_invalid_or_out_of_range_samples_are_rejected(): void
    {
        $payload = [
            'route_name' => 'people/index',
            'metric' => 'CLS',
            'value' => 11,
            'delta' => 0.1,
            'device' => 'desktop',
            'navigation_type' => 'reload',
        ];

        $response = $this->postJson(route('performance.web-vitals.store'), $payload);
        $response->assertUnprocessable();
        $this->assertStringContainsString('value', $response->getContent());

        $response = $this->postJson(route('performance.web-vitals.store'), [
            ...$payload,
            'route_name' => '/people/42?email=private@example.com',
            'metric' => 'LCP',
            'value' => 20,
        ]);
        $response->assertUnprocessable();
        $this->assertStringContainsString('route_name', $response->getContent());

        $response = $this->postJson(route('performance.web-vitals.store'), [
            ...$payload,
            'metric' => 'FID',
            'value' => 20,
        ]);
        $response->assertUnprocessable();
        $this->assertStringContainsString('metric', $response->getContent());

        $this->assertDatabaseCount('web_vital_samples', 0);
    }

    public function test_the_sampling_rate_is_shared_with_the_inertia_client(): void
    {
        config(['performance.web_vitals_sample_rate' => 0.25]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('performance.webVitalsSampleRate', 0.25)
            );
    }

    public function test_the_web_vitals_report_can_group_eligible_samples(): void
    {
        foreach ([100, 150, 200, 250, 300] as $value) {
            WebVitalSample::create([
                'route_name' => 'dashboard',
                'metric' => 'INP',
                'rating' => $value <= 200 ? 'good' : 'needs-improvement',
                'value' => $value,
                'delta' => $value,
                'device' => 'desktop',
                'navigation_type' => 'navigate',
                'occurred_at' => now(),
            ]);
        }

        $this->artisan('performance:web-vitals-report', ['--days' => 7, '--minimum' => 5])
            ->expectsTable(
                ['Route', 'Metric', 'Device', 'Samples', 'p50', 'p75', 'p95', 'Good'],
                [['dashboard', 'INP', 'desktop', 5, '200 ms', '250 ms', '300 ms', '60.0%']],
            )
            ->assertSuccessful();
    }
}
