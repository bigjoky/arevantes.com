<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectsMobileTest extends TestCase
{
    use RefreshDatabase;

    public function test_technical_projection_is_registered_and_specific_to_the_service(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'technician']));
        foreach ([
            'solar' => ['panel_count' => 12, 'inverter_brand' => 'Sintético', 'battery_capacity_kwh' => null],
            'electrical' => ['electrical_scope' => 'Cuadro', 'electrical_voltage_v' => 230, 'electrical_phase' => 'single_phase', 'contracted_power_kw' => 5, 'electrical_certificate_required' => true],
            'security' => ['security_system_type' => 'Alarma', 'security_zones' => 4, 'security_monitoring_mode' => 'local', 'security_grade' => 'grade_2'],
            'ev_charger' => ['charger_power_kw' => 7, 'charger_connector_type' => 'Type 2'],
            'hvac' => ['hvac_system_type' => 'Split'],
        ] as $service => $fields) {
            $installation = Installation::create(['project_reference' => 'QA-'.$service, 'site_name' => 'Sintética', 'site_address_line_1' => 'Calle Sintética 1', 'service_type' => $service, 'status' => 'approved', ...$fields]);
            $this->get('/installations/'.$installation->id)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('installations/show')->has('workspace.technical', count($fields))
                ->where('workspace.technical.0.value', $installation->{array_key_first($fields)})
                ->where('workspace.permissions.manage', false)->where('workspace.permissions.finance', false));
        }
    }

    public function test_missing_solar_values_remain_unknown_and_tabs_keep_server_permissions(): void
    {
        $installation = Installation::create(['project_reference' => 'QA-EMPTY', 'site_name' => 'Sin datos', 'site_address_line_1' => 'Calle Sintética 1', 'service_type' => 'solar', 'status' => 'approved']);
        $this->actingAs(User::factory()->create(['role' => 'technician']))
            ->get('/execution/projects/'.$installation->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('technical.0.value', null)->where('technical.1.value', null)->where('technical.2.value', null)
            ->where('installation.system_power_kw', null));
        $this->get('/execution/projects/'.$installation->id.'?tab=costs')->assertForbidden();
        $this->get('/execution/projects/'.$installation->id.'?tab=quote')->assertForbidden();
    }
}
