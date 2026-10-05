<?php

namespace App\Services\Tax;

/** Read-only candidates, never a declaration or a substitute for fiscal classification. */
class Model303Preview
{
    public const SOURCE = 'https://sede.agenciatributaria.gob.es/Sede/todas-gestiones/impuestos-tasas/iva/modelo-303-iva-autoliquidacion_/instrucciones-2026/instrucciones-02-12-2t-4t-2026.html';

    public function make(array $report, array $documents, array $classifications = []): array
    {
        // Do not silently reuse this period-specific design for another fiscal version.
        $supported = $report['period']['year'] === 2026 && in_array($report['period']['quarter'], [2, 3, 4], true);
        $groups = [
            ['base_box' => '01', 'rate_box' => '02', 'vat_box' => '03', 'rate' => 4],
            ['base_box' => '04', 'rate_box' => '05', 'vat_box' => '06', 'rate' => 10],
            ['base_box' => '07', 'rate_box' => '08', 'vat_box' => '09', 'rate' => 21],
            ['base_box' => '14', 'rate_box' => null, 'vat_box' => '15', 'rate' => null],
        ];
        foreach ($groups as &$group) {
            $group += ['base' => null, 'vat' => null, 'documents' => []];
        }
        unset($group);
        $pending = [];
        foreach ($report['issued'] as $row) {
            $document = $documents[$row['source_id']] ?? [];
            $rate = (float) $row['rate'];
            $country = strtoupper(trim((string) ($document['country'] ?? '')));
            $eligible = $supported && $row['source_type'] === 'sales_document'
                && $country === 'ES' && in_array($rate, [4.0, 10.0, 21.0], true)
                && in_array($document['type'] ?? null, ['invoice', 'credit_note'], true);
            if (! $eligible) {
                $pending[] = $row;

                continue;
            }
            $index = $document['type'] === 'credit_note' ? 3 : array_search($rate, [4.0, 10.0, 21.0], true);
            // Preserve the supplier/customer document's rounded amounts; do not recompute VAT.
            $groups[$index]['base'] = ($groups[$index]['base'] ?? 0) + (int) round((float) $row['base'] * 100);
            $groups[$index]['vat'] = ($groups[$index]['vat'] ?? 0) + (int) round((float) $row['vat'] * 100);
            $groups[$index]['documents'][] = $row;
        }
        foreach ($groups as &$group) {
            $group['base'] = $group['base'] === null ? null : $group['base'] / 100;
            $group['vat'] = $group['vat'] === null ? null : $group['vat'] / 100;
        }
        unset($group);

        $deductions = [
            ['base_box' => '28', 'vat_box' => '29', 'operation_class' => 'domestic_current', 'base' => null, 'vat' => null, 'documents' => []],
            ['base_box' => '30', 'vat_box' => '31', 'operation_class' => 'domestic_investment', 'base' => null, 'vat' => null, 'documents' => []],
        ];
        $pendingReceived = [];
        $deferredReceived = [];
        foreach ($report['received'] as $row) {
            $classification = $classifications[$row['source_type'].':'.$row['source_id']] ?? [];
            $class = $classification['operation_class'] ?? null;
            if ($row['source_type'] === 'purchase_document' && ($classification['current'] ?? false)) {
                $row = [...$row, 'registered_on' => $classification['registered_on'] ?? null,
                    'deduction_year' => $classification['deduction_year'] ?? null,
                    'deduction_quarter' => $classification['deduction_quarter'] ?? null];
                if ($row['deduction_year'] !== $report['period']['year'] || $row['deduction_quarter'] !== $report['period']['quarter']) {
                    $deferredReceived[] = $row;

                    continue;
                }
            }
            if (! $supported || ! ($classification['current'] ?? false) || ! in_array($row['source_type'], ['expense', 'purchase_document'], true)
                || ! in_array($class, ['domestic_current', 'domestic_investment'], true)
                || (float) $row['vat'] <= 0 || (float) ($row['deductible_vat'] ?? -1) <= 0) {
                $pendingReceived[] = $row['source_type'] === 'purchase_document'
                    ? [...$row, 'fiscal_issues' => $classification['issues'] ?? ['purchase_review_pending']]
                    : $row;

                continue;
            }
            $index = $class === 'domestic_current' ? 0 : 1;
            // Bases are not prorated. Only the documented deductible quota is used.
            $deductions[$index]['base'] = ($deductions[$index]['base'] ?? 0) + (int) round((float) $row['base'] * 100);
            $deductions[$index]['vat'] = ($deductions[$index]['vat'] ?? 0) + (int) round((float) $row['deductible_vat'] * 100);
            $deductions[$index]['documents'][] = $row;
        }
        foreach ($deductions as &$deduction) {
            $deduction['base'] = $deduction['base'] === null ? null : $deduction['base'] / 100;
            $deduction['vat'] = $deduction['vat'] === null ? null : $deduction['vat'] / 100;
        }
        unset($deduction);

        return [
            'period' => $report['period'], 'version' => '303-2026-2T-4T-v3',
            'source' => self::SOURCE, 'supported' => $supported, 'ready' => false,
            'groups' => $groups, 'pendingIssued' => $pending,
            'deductions' => $deductions, 'pendingReceived' => $pendingReceived, 'deferredReceived' => $deferredReceived,
        ];
    }
}
