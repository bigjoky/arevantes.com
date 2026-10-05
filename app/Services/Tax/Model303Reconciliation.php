<?php

namespace App\Services\Tax;

/** Diagnostic only: equality is not approval, settlement or official filing readiness. */
class Model303Reconciliation
{
    public function make(array $books, array $preview): array
    {
        $original = $this->documents($books['issued'], $books['received']);
        $candidate = $this->documents(
            collect($preview['groups'])->flatMap(fn ($group) => $group['documents']),
            collect($preview['deductions'])->flatMap(fn ($group) => $group['documents']),
        );
        $deferred = collect($preview['deferredReceived'])->mapWithKeys(fn ($row) => [$row['source_type'].':'.$row['source_id'] => true]);
        $differences = [];
        foreach (array_unique([...array_keys($original), ...array_keys($candidate)]) as $key) {
            $before = $original[$key] ?? null;
            $after = $candidate[$key] ?? null;
            $delta = ($after['cents'] ?? 0) - ($before['cents'] ?? 0);
            if ($delta === 0) {
                continue;
            }
            $row = $after ?? $before;
            $differences[] = [
                'source_type' => $row['source_type'], 'source_id' => $row['source_id'],
                'number' => $row['number'] ?? '—', 'direction' => $row['direction'],
                'book_vat' => ($before['cents'] ?? 0) / 100, 'candidate_vat' => ($after['cents'] ?? 0) / 100,
                'difference' => $delta / 100,
                'reason' => ! $before ? 'added_period' : (! $after ? ($deferred->has($key) ? 'deferred_period' : 'pending_review') : 'quota_difference'),
            ];
        }
        $rows = [];
        foreach (['output' => 'outputVat', 'input' => 'deductibleVat'] as $direction => $field) {
            $projection = array_sum(array_column(array_filter($candidate, fn ($row) => $row['direction'] === $direction), 'cents'));
            $book = isset($books['summary'][$field]) ? $this->cents($books['summary'][$field]) : null;
            $ledgerKey = $direction === 'output' ? 'outputVat' : 'inputVat';
            $ledger = isset($books['accountingCheck'][$ledgerKey]) ? $this->cents($books['accountingCheck'][$ledgerKey]) : null;
            $explained = array_sum(array_map(fn ($row) => $this->cents($row['difference']), array_filter($differences, fn ($row) => $row['direction'] === $direction)));
            $rows[] = ['direction' => $direction, 'candidate_vat' => $projection / 100,
                'book_vat' => $book === null ? null : $book / 100, 'ledger_vat' => $ledger === null ? null : $ledger / 100,
                'book_difference' => $book === null ? null : ($projection - $book) / 100,
                'ledger_difference' => $ledger === null ? null : ($projection - $ledger) / 100,
                'unexplained_difference' => $book === null ? null : ($projection - $book - $explained) / 100];
        }
        $pending = count($preview['pendingIssued']) + count($preview['pendingReceived']);
        $matches = $pending === 0 && $preview['supported'] && collect($rows)->every(fn ($row) => $row['book_difference'] === 0 && $row['ledger_difference'] === 0 && $row['unexplained_difference'] === 0);

        return ['rows' => $rows, 'differences' => $differences, 'pending_rows' => $pending,
            'matches' => $matches, 'ready' => false];
    }

    private function documents(iterable $issued, iterable $received): array
    {
        $result = [];
        foreach (['output' => $issued, 'input' => $received] as $direction => $rows) {
            foreach ($rows as $row) {
                $key = $row['source_type'].':'.$row['source_id'];
                $result[$key] ??= [...$row, 'direction' => $direction, 'cents' => 0];
                $result[$key]['cents'] += $this->cents($direction === 'output' ? $row['vat'] : ($row['deductible_vat'] ?? 0));
            }
        }

        return $result;
    }

    private function cents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }
}
