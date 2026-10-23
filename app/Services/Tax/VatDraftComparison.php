<?php

namespace App\Services\Tax;

class VatDraftComparison
{
    public function make(array $before, array $after): array
    {
        $before = json_decode(json_encode($before, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $after = json_decode(json_encode($after, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $totals = [];
        foreach (['outputVat', 'supportedVat', 'deductibleVat', 'nonDeductibleVat', 'result'] as $field) {
            $old = (float) ($before['summary'][$field] ?? 0);
            $new = (float) ($after['summary'][$field] ?? 0);
            $totals[$field] = ['before' => $old, 'after' => $new, 'difference' => round($new - $old, 2)];
        }
        $oldDocuments = $this->documents($before);
        $newDocuments = $this->documents($after);
        $counts = ['added' => 0, 'removed' => 0, 'modified' => 0];
        $changes = [];
        $keys = array_unique([...array_keys($oldDocuments), ...array_keys($newDocuments)]);
        sort($keys);
        foreach ($keys as $key) {
            $old = $oldDocuments[$key] ?? null;
            $new = $newDocuments[$key] ?? null;
            if ($old === $new) {
                continue;
            }
            $kind = $old === null ? 'added' : ($new === null ? 'removed' : 'modified');
            $counts[$kind]++;
            if (count($changes) < 50) {
                $changes[] = ['key' => $key, 'kind' => $kind, 'before' => $this->summary($old), 'after' => $this->summary($new)];
            }
        }

        return ['totals' => $totals, 'counts' => $counts, 'documents' => $changes,
            'truncated' => array_sum($counts) > count($changes),
            'accounting_changed' => ($before['accountingCheck'] ?? []) !== ($after['accountingCheck'] ?? [])];
    }

    private function documents(array $data): array
    {
        $documents = [];
        foreach (['issued', 'received'] as $book) {
            foreach ($data[$book] ?? [] as $row) {
                $documents[$row['source_type'].':'.$row['source_id']][] = $row;
            }
        }
        foreach ($documents as &$rows) {
            usort($rows, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
        }

        return $documents;
    }

    private function summary(?array $rows): ?array
    {
        if ($rows === null) {
            return null;
        }

        return ['number' => $rows[0]['number'], 'party' => $rows[0]['party'], 'date' => $rows[0]['date'], 'tax_id' => $rows[0]['tax_id'] ?? null,
            'base' => round(array_sum(array_column($rows, 'base')), 2),
            'vat' => round(array_sum(array_column($rows, 'vat')), 2),
            'deductible_vat' => round(array_sum(array_column($rows, 'deductible_vat')), 2),
            'breakdown' => array_map(fn ($row) => ['rate' => $row['rate'] ?? 0, 'base' => $row['base'], 'vat' => $row['vat'], 'deductible_vat' => $row['deductible_vat'] ?? null], $rows)];
    }
}
