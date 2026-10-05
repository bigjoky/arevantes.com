<?php

namespace App\Services\Tax;

use App\Models\TaxReturn;

/** Existing accounting settlement evidence is not an official 303 declaration. */
class Model303SavedComparison
{
    public function make(?TaxReturn $return, array $books, array $reconciliation): ?array
    {
        if (! $return) {
            return null;
        }
        $sources = $return->snapshot['sources'] ?? [];
        $available = ($return->snapshot['version'] ?? null) === 'vat-settlement-v1'
            && is_array($sources) && isset($sources['issued'], $sources['received'], $sources['summary'], $sources['accountingCheck'])
            && is_array($sources['issued']) && is_array($sources['received']) && is_array($sources['summary']) && is_array($sources['accountingCheck'])
            && collect(['outputVat', 'supportedVat', 'deductibleVat', 'nonDeductibleVat', 'result'])
                ->every(fn ($field) => is_numeric($sources['summary'][$field] ?? null))
            && is_numeric($sources['accountingCheck']['outputVat'] ?? null) && is_numeric($sources['accountingCheck']['inputVat'] ?? null)
            && ($sources['period']['year'] ?? null) === $books['period']['year']
            && ($sources['period']['quarter'] ?? null) === $books['period']['quarter'];
        $comparison = $available ? app(VatDraftComparison::class)->make($sources, $books) : null;
        $candidate = collect($reconciliation['rows'])->keyBy('direction');
        $values = ['output' => $candidate['output']['candidate_vat'], 'input' => $candidate['input']['candidate_vat']];
        $values['net'] = ($this->cents($values['output']) - $this->cents($values['input'])) / 100;
        $totals = [];
        foreach (['output' => 'output_vat', 'input' => 'input_vat', 'net' => 'result'] as $key => $field) {
            $saved = $return->$field === null ? null : $this->cents($return->$field);
            $totals[] = ['key' => $key, 'saved' => $saved === null ? null : $saved / 100,
                'candidate' => $values[$key], 'difference' => $saved === null ? null : ($this->cents($values[$key]) - $saved) / 100];
        }
        $booksUnchanged = $available && array_sum($comparison['counts']) === 0 && ! $comparison['accounting_changed']
            && collect($comparison['totals'])->every(fn ($total) => $total['difference'] === 0.0);

        return ['id' => $return->id, 'status' => $return->status,
            'protected' => in_array($return->status, ['prepared', 'filed'], true),
            'evidence_available' => $available, 'books_unchanged' => $booksUnchanged,
            'totals' => $totals, 'book_changes' => $comparison, 'ready' => false];
    }

    private function cents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }
}
