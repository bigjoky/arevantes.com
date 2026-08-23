<?php

namespace Tests\Feature;

use App\Models\FieldServiceProfile;
use App\Models\HrEmploymentProfile;
use App\Models\HrLeaveRequest;
use App\Models\Installation;
use App\Models\Person;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Planning\WorkforceCapacityForecast;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceCapacityForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_forecast_aligns_planned_work_with_approved_hr_availability(): void
    {
        $person = Person::create([
            'category' => 'staff',
            'first_name' => 'Alex',
            'last_name' => 'Vega',
        ]);
        $technician = User::factory()->create([
            'person_id' => $person->id,
            'role' => 'technician',
            'name' => 'Alex Vega',
        ]);
        HrEmploymentProfile::create([
            'person_id' => $person->id,
            'status' => 'active',
            'weekly_hours' => 40,
            'start_date' => '2026-01-01',
        ]);
        FieldServiceProfile::create([
            'user_id' => $technician->id,
            'daily_capacity_minutes' => 480,
            'is_available' => true,
        ]);
        HrLeaveRequest::create([
            'person_id' => $person->id,
            'type' => 'vacation',
            'start_date' => '2026-08-12',
            'end_date' => '2026-08-12',
            'requested_days' => 1,
            'status' => 'approved',
            'notes' => 'This sensitive reason must not be serialized.',
        ]);
        HrLeaveRequest::create([
            'person_id' => $person->id,
            'type' => 'personal',
            'start_date' => '2026-08-13',
            'end_date' => '2026-08-13',
            'requested_days' => 1,
            'status' => 'pending',
        ]);
        $installation = Installation::create([
            'service_type' => 'solar',
            'status' => 'scheduled',
            'site_address_line_1' => 'Calle Solar 8',
        ]);
        WorkOrder::create([
            'installation_id' => $installation->id,
            'assigned_user_id' => $technician->id,
            'number' => 'WO-CAPACITY-1',
            'title' => 'Commissioning',
            'status' => 'planned',
            'scheduled_start' => '2026-08-11 09:00:00',
            'scheduled_end' => '2026-08-11 17:00:00',
        ]);

        $forecast = app(WorkforceCapacityForecast::class)->forecast(CarbonImmutable::parse('2026-08-10'), 1);

        $this->assertSame(2400, $forecast['summary']['capacity_minutes']);
        $this->assertSame(480, $forecast['summary']['unavailable_minutes']);
        $this->assertSame(1920, $forecast['summary']['available_minutes']);
        $this->assertSame(480, $forecast['summary']['planned_minutes']);
        $this->assertSame(25.0, $forecast['summary']['utilization_percent']);
        $this->assertArrayNotHasKey('notes', $forecast['weeks'][0]['technicians'][0]);
    }

    public function test_technician_forecast_is_scoped_to_the_viewer(): void
    {
        $viewer = User::factory()->create(['role' => 'technician']);
        User::factory()->create(['role' => 'technician']);

        $forecast = app(WorkforceCapacityForecast::class)->forecast(CarbonImmutable::parse('2026-08-10'), 1, $viewer);

        $this->assertCount(1, $forecast['weeks'][0]['technicians']);
        $this->assertSame($viewer->id, $forecast['weeks'][0]['technicians'][0]['user_id']);
    }
}
