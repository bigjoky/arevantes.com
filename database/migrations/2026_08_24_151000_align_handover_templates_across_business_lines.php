<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach ($this->templates() as $template) {
            DB::table('work_order_templates')->insertOrIgnore([
                ...$template,
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
    }

    /** @return array<int, array<string, mixed>> */
    private function templates(): array
    {
        $lines = [
            'solar' => [
                'label' => 'Solar',
                'skills' => ['solar', 'electrical'],
                'documents' => [['key' => 'commissioning_report', 'label' => 'Commissioning and production test report'], ['key' => 'electrical_certificate', 'label' => 'Electrical certificate when applicable'], ['key' => 'monitoring_guide', 'label' => 'Monitoring access and user guide']],
            ],
            'hvac' => [
                'label' => 'HVAC',
                'skills' => ['hvac', 'refrigeration'],
                'documents' => [['key' => 'commissioning_report', 'label' => 'Commissioning and operating test report'], ['key' => 'refrigerant_record', 'label' => 'Refrigerant and leak-test record'], ['key' => 'maintenance_plan', 'label' => 'Maintenance plan and user guide']],
            ],
            'ev_charger' => [
                'label' => 'EV charging',
                'skills' => ['ev_charger', 'electrical'],
                'documents' => [['key' => 'test_report', 'label' => 'Protection and charging test report'], ['key' => 'electrical_certificate', 'label' => 'Electrical certificate when applicable'], ['key' => 'charging_guide', 'label' => 'Charging access and user guide']],
            ],
        ];
        $common = [['key' => 'work_report', 'label' => 'Signed work report'], ['key' => 'equipment_manuals', 'label' => 'Equipment manuals and warranties']];
        $templates = [];

        foreach ($lines as $serviceType => $line) {
            $templates[] = $this->template($line['label'].' · Technical survey', $serviceType, 'survey', $line['label'].' technical survey', 120, $line['skills'], [], [
                ['key' => 'site_overview', 'label' => 'Site and work-area overview', 'kind' => 'photo', 'min_count' => 1],
                ['key' => 'existing_services', 'label' => 'Existing services and installation constraints', 'kind' => 'photo', 'min_count' => 1],
            ]);
            $templates[] = $this->template($line['label'].' · Installation', $serviceType, 'installation', $line['label'].' installation', 480, $line['skills'], [...$common, ...$line['documents']], [
                ['key' => 'before_work', 'label' => 'Area before work', 'kind' => 'photo', 'min_count' => 1],
                ['key' => 'final_installation', 'label' => 'Finished installation', 'kind' => 'photo', 'min_count' => 1],
            ]);
            $templates[] = $this->template($line['label'].' · Commissioning', $serviceType, 'commissioning', $line['label'].' commissioning and handover', 180, [...$line['skills'], 'commissioning'], [...$common, ...$line['documents']], [
                ['key' => 'functional_test', 'label' => 'Functional test result', 'kind' => 'photo', 'min_count' => 1],
                ['key' => 'customer_handover', 'label' => 'Installation ready for customer handover', 'kind' => 'photo', 'min_count' => 1],
            ]);
        }

        return $templates;
    }

    /** @param array<int, string> $skills @param array<int, array<string, string>> $documents @param array<int, array<string, mixed>> $evidence */
    private function template(string $name, string $serviceType, string $kind, string $title, int $duration, array $skills, array $documents, array $evidence): array
    {
        $checks = $kind === 'survey'
            ? ['Confirm customer requirements and scope', 'Inspect existing services and access', 'Record measurements and constraints', 'Confirm permits and documentation required']
            : ['Confirm approved scope and safe working conditions', 'Record equipment and serial numbers', 'Complete functional and safety tests', 'Explain operation and maintenance to the customer', 'Confirm the complete handover package'];

        return [
            'name' => $name,
            'service_type' => $serviceType,
            'kind' => $kind,
            'title' => $title,
            'estimated_duration_minutes' => $duration,
            'required_skills' => array_values(array_unique($skills)),
            'checklist' => array_map(fn (string $label) => ['label' => $label, 'is_required' => true], $checks),
            'required_evidence' => $evidence,
            'handover_documents' => $documents,
        ];
    }
};
