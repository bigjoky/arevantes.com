<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\Person;
use App\Models\User;
use App\Services\Work\ChecklistTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BusinessLinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_electrical_and_security_installations_keep_their_own_technical_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Person::create(['category' => 'customer', 'first_name' => 'Cliente']);
        $base = [
            'person_id' => $customer->id,
            'status' => 'lead',
            'site_address_line_1' => 'Calle Prueba',
            'site_country' => 'Spain',
        ];

        $this->actingAs($admin)->post(route('installations.store'), [
            ...$base,
            'service_type' => 'electrical',
            'electrical_scope' => 'Cuadro general y circuitos',
            'electrical_voltage_v' => 400,
            'electrical_phase' => 'three_phase',
            'contracted_power_kw' => 15.5,
            'electrical_certificate_required' => true,
        ])->assertRedirect();

        $this->post(route('installations.store'), [
            ...$base,
            'service_type' => 'security',
            'security_system_type' => 'Alarma de intrusión y CCTV',
            'security_zones' => 12,
            'security_monitoring_mode' => 'alarm_receiving_centre',
            'security_grade' => 'grade_2',
        ])->assertRedirect();

        $electrical = Installation::where('service_type', 'electrical')->firstOrFail();
        $security = Installation::where('service_type', 'security')->firstOrFail();

        $this->assertSame(400, $electrical->electrical_voltage_v);
        $this->assertSame('three_phase', $electrical->electrical_phase);
        $this->assertTrue($electrical->electrical_certificate_required);
        $this->assertSame('Alarma de intrusión y CCTV', $security->security_system_type);
        $this->assertSame(12, $security->security_zones);
        $this->assertSame('grade_2', $security->security_grade);

        $this->patch(route('installations.update', $electrical), [
            ...$base,
            'service_type' => 'security',
            'security_system_type' => 'Control de accesos',
            'security_monitoring_mode' => 'local',
        ])->assertRedirect();

        $electrical->refresh();
        $this->assertSame('security', $electrical->service_type);
        $this->assertNull($electrical->electrical_scope);
        $this->assertNull($electrical->electrical_voltage_v);
        $this->assertFalse($electrical->electrical_certificate_required);
    }

    public function test_each_new_business_line_has_an_operational_checklist(): void
    {
        $template = app(ChecklistTemplate::class);
        $electrical = collect($template->for('electrical'))->pluck('label');
        $security = collect($template->for('security'))->pluck('label');

        $this->assertTrue($electrical->contains('Record certificate and functional test'));
        $this->assertTrue($security->contains('Complete alarm, recording and handover tests'));
    }

    public function test_the_public_website_accepts_electrical_and_security_leads(): void
    {
        Notification::fake();

        foreach ([
            ['electrical', 'Instalaciones eléctricas'],
            ['security', 'Alarmas y sistemas de seguridad'],
        ] as $index => [$service, $label]) {
            $this->post(route('website.contact'), [
                'name' => 'Cliente '.$index,
                'email' => "linea{$index}@example.com",
                'phone' => '60012312'.$index,
                'postal_code' => '14001',
                'service' => $service,
                'message' => 'Necesito estudiar una nueva instalación para mi negocio.',
                'privacy' => '1',
                'company_url' => '',
            ])->assertRedirect(route('home'));

            $this->assertDatabaseHas('opportunities', [
                'title' => 'Solicitud web · '.$label,
                'source' => 'website',
            ]);
        }
    }
}
