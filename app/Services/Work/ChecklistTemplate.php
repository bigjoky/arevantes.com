<?php

namespace App\Services\Work;

class ChecklistTemplate
{
    public function for(string $serviceType): array
    {
        $common = ['Confirm work area is safe and isolated', 'Photograph completed installation', 'Clean work area and remove waste', 'Explain operation to customer'];
        $specific = match ($serviceType) {
            'solar' => ['Verify mounting and roof sealing', 'Check DC polarity and insulation', 'Record inverter and module serial numbers', 'Verify protections and earthing', 'Commission monitoring and production'],
            'ev_charger' => ['Verify dedicated circuit and protections', 'Check earthing and insulation', 'Record charger serial number', 'Configure power balancing', 'Complete charging test'],
            'hvac' => ['Verify condensate drainage', 'Perform pressure and leak test', 'Record indoor and outdoor unit serials', 'Check operating temperatures', 'Explain filters and maintenance'],
            default => ['Verify cable sizing and routing', 'Test protections and earthing', 'Measure insulation and continuity', 'Label circuits and update panel schedule', 'Complete functional test'],
        };

        return array_map(fn ($label) => ['label' => $label, 'is_required' => true], [...$specific, ...$common]);
    }
}
