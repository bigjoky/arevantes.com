<?php

namespace App\Services\Work;

class ChecklistTemplate
{
    public function for(string $serviceType, string $kind = 'installation'): array
    {
        $common = ['Confirm work area is safe and isolated', 'Photograph completed work', 'Clean work area and remove waste', 'Explain outcome to customer'];
        $byKind = match ($kind) {
            'survey' => ['Confirm customer requirements', 'Photograph site and access', 'Record measurements and electrical constraints', 'Identify risks and permits'],
            'commissioning' => ['Verify installation documentation', 'Record commissioning measurements', 'Configure monitoring', 'Complete functional tests'],
            'maintenance' => ['Review previous service history', 'Inspect and clean equipment', 'Record operating measurements', 'List preventive recommendations'],
            'repair' => ['Confirm reported fault', 'Record diagnosis and root cause', 'Record replaced parts', 'Complete recurrence test'],
            default => [],
        };
        $byService = match ($serviceType) {
            'solar' => ['Verify mounting and roof sealing', 'Check DC polarity and insulation', 'Record inverter and module serial numbers', 'Verify protections and earthing', 'Commission monitoring and production'],
            'ev_charger' => ['Verify dedicated circuit and protections', 'Check earthing and insulation', 'Record charger serial number', 'Configure power balancing', 'Complete charging test'],
            'hvac' => ['Verify condensate drainage', 'Perform pressure and leak test', 'Record indoor and outdoor unit serials', 'Check operating temperatures', 'Explain filters and maintenance'],
            default => ['Verify cable sizing and routing', 'Test protections and earthing', 'Measure insulation and continuity', 'Label circuits and update panel schedule', 'Complete functional test'],
        };

        return array_map(fn ($label) => ['label' => $label, 'is_required' => true], [...$byKind, ...$byService, ...$common]);
    }
}
