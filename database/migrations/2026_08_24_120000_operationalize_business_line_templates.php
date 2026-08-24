<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('service_type')->nullable()->after('person_id')->index();
        });
        Schema::table('quote_versions', function (Blueprint $table) {
            $table->string('service_type')->nullable()->after('created_by');
        });
        Schema::table('work_order_templates', function (Blueprint $table) {
            $table->string('service_type')->nullable()->after('name')->index();
            $table->json('required_evidence')->nullable()->after('checklist');
            $table->json('handover_documents')->nullable()->after('required_evidence');
            $table->boolean('is_system')->default(false)->after('is_active');
        });
        Schema::table('work_orders', function (Blueprint $table) {
            $table->json('required_evidence')->nullable()->after('required_skills');
            $table->json('handover_documents')->nullable()->after('required_evidence');
        });
        Schema::table('work_order_attachments', function (Blueprint $table) {
            $table->string('evidence_key', 100)->nullable()->after('kind')->index();
        });

        $now = now();
        foreach ($this->templates() as $template) {
            DB::table('work_order_templates')->insertOrIgnore([...$template,
                'required_skills' => json_encode($template['required_skills'], JSON_THROW_ON_ERROR),
                'checklist' => json_encode($template['checklist'], JSON_THROW_ON_ERROR),
                'required_evidence' => json_encode($template['required_evidence'], JSON_THROW_ON_ERROR),
                'handover_documents' => json_encode($template['handover_documents'], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('work_order_templates')->where('is_system', true)->whereIn('name', collect($this->templates())->pluck('name'))->delete();
        Schema::table('work_order_attachments', fn (Blueprint $table) => $table->dropColumn('evidence_key'));
        Schema::table('work_orders', fn (Blueprint $table) => $table->dropColumn(['required_evidence', 'handover_documents']));
        Schema::table('work_order_templates', function (Blueprint $table) {
            $table->dropIndex(['service_type']);
            $table->dropColumn(['service_type', 'required_evidence', 'handover_documents', 'is_system']);
        });
        Schema::table('quote_versions', fn (Blueprint $table) => $table->dropColumn('service_type'));
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['service_type']);
            $table->dropColumn('service_type');
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function templates(): array
    {
        $commonHandover = [
            ['key' => 'work_report', 'label' => 'Signed work report'],
            ['key' => 'equipment_manuals', 'label' => 'Equipment manuals'],
        ];

        return [
            [
                'name' => 'Electrical · Technical survey', 'service_type' => 'electrical', 'kind' => 'survey',
                'title' => 'Electrical technical survey', 'estimated_duration_minutes' => 120,
                'required_skills' => ['electrical', 'electrical_survey'],
                'checklist' => $this->checks(['Confirm supply and contracted power', 'Inspect service entrance, panel and protections', 'Record voltage, phase and earthing constraints', 'Measure routes and identify affected circuits', 'Confirm certificate and permit requirements']),
                'required_evidence' => $this->photos(['site_overview' => 'Site and work-area overview', 'existing_panel' => 'Existing panel and protections', 'supply_point' => 'Supply point and meter']),
                'handover_documents' => [],
            ],
            [
                'name' => 'Electrical · Installation', 'service_type' => 'electrical', 'kind' => 'installation',
                'title' => 'Electrical installation', 'estimated_duration_minutes' => 480,
                'required_skills' => ['electrical', 'electrical_installation', 'electrical_safety'],
                'checklist' => $this->checks(['Confirm isolation and lockout', 'Verify conductor sizing and routing', 'Install and label protections and circuits', 'Test earthing, insulation and continuity', 'Record final measurements and as-built changes']),
                'required_evidence' => $this->photos(['before_work' => 'Area before work', 'protections' => 'Installed protections and labels', 'final_installation' => 'Finished electrical installation']),
                'handover_documents' => [...$commonHandover, ['key' => 'test_report', 'label' => 'Electrical test report'], ['key' => 'circuit_schedule', 'label' => 'As-built circuit schedule'], ['key' => 'electrical_certificate', 'label' => 'Electrical certificate when applicable']],
            ],
            [
                'name' => 'Electrical · Commissioning', 'service_type' => 'electrical', 'kind' => 'commissioning',
                'title' => 'Electrical commissioning and handover', 'estimated_duration_minutes' => 180,
                'required_skills' => ['electrical', 'commissioning', 'electrical_testing'],
                'checklist' => $this->checks(['Verify installation documentation', 'Record protection and circuit test results', 'Complete energisation and functional tests', 'Explain operation and safe isolation to customer', 'Confirm handover documentation']),
                'required_evidence' => $this->photos(['test_results' => 'Recorded test results', 'energised_panel' => 'Energised panel and final labels', 'customer_handover' => 'Completed installation at handover']),
                'handover_documents' => [...$commonHandover, ['key' => 'test_report', 'label' => 'Electrical test report'], ['key' => 'electrical_certificate', 'label' => 'Electrical certificate when applicable']],
            ],
            [
                'name' => 'Security · Technical survey', 'service_type' => 'security', 'kind' => 'survey',
                'title' => 'Security system technical survey', 'estimated_duration_minutes' => 120,
                'required_skills' => ['security_systems', 'security_survey'],
                'checklist' => $this->checks(['Confirm risks, users and operating expectations', 'Map entrances, detection zones and camera coverage', 'Review network, power and communication paths', 'Identify privacy-sensitive areas and retention needs', 'Confirm monitoring and handover requirements']),
                'required_evidence' => $this->photos(['site_overview' => 'Site overview', 'access_points' => 'Entrances and access points', 'coverage_constraints' => 'Coverage and installation constraints']),
                'handover_documents' => [],
            ],
            [
                'name' => 'Security · Installation', 'service_type' => 'security', 'kind' => 'installation',
                'title' => 'Alarm and security system installation', 'estimated_duration_minutes' => 480,
                'required_skills' => ['security_systems', 'alarm_installation', 'cctv'],
                'checklist' => $this->checks(['Confirm approved coverage and privacy zones', 'Install and label panel, detectors, cameras and access devices', 'Record equipment serial numbers and locations', 'Test power-loss, tamper and communication alerts', 'Configure authorised users with least privilege']),
                'required_evidence' => $this->photos(['before_work' => 'Area before work', 'control_equipment' => 'Control panel and communication equipment', 'installed_devices' => 'Installed detectors, cameras or access devices']),
                'handover_documents' => [...$commonHandover, ['key' => 'device_schedule', 'label' => 'Device and zone schedule'], ['key' => 'privacy_configuration', 'label' => 'Privacy and recording configuration'], ['key' => 'monitoring_certificate', 'label' => 'Monitoring connection document when applicable']],
            ],
            [
                'name' => 'Security · Commissioning', 'service_type' => 'security', 'kind' => 'commissioning',
                'title' => 'Security system commissioning and handover', 'estimated_duration_minutes' => 180,
                'required_skills' => ['security_systems', 'commissioning', 'customer_training'],
                'checklist' => $this->checks(['Test every zone, detector, camera and access point', 'Verify tamper, outage and communications behaviour', 'Confirm recording, privacy masks and authorised access', 'Train customer on arming, alerts and user management', 'Confirm handover documents and support contacts']),
                'required_evidence' => $this->photos(['functional_test' => 'Functional test result', 'final_devices' => 'Final device installation', 'customer_handover' => 'System ready for customer handover']),
                'handover_documents' => [...$commonHandover, ['key' => 'commissioning_report', 'label' => 'Commissioning and functional test report'], ['key' => 'device_schedule', 'label' => 'Device and zone schedule'], ['key' => 'user_guide', 'label' => 'Customer user guide']],
            ],
        ];
    }

    /** @param array<int, string> $labels */
    private function checks(array $labels): array
    {
        return array_map(fn (string $label) => ['label' => $label, 'is_required' => true], $labels);
    }

    /** @param array<string, string> $items */
    private function photos(array $items): array
    {
        return collect($items)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label, 'kind' => 'photo', 'min_count' => 1])->values()->all();
    }
};
