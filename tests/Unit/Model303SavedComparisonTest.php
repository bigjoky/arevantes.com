<?php

namespace Tests\Unit;

use App\Models\TaxReturn;
use App\Services\Tax\Model303SavedComparison;
use PHPUnit\Framework\TestCase;

class Model303SavedComparisonTest extends TestCase
{
    private function books(): array
    {
        return ['period' => ['year' => 2026, 'quarter' => 3],
            'issued' => [], 'received' => [['source_type' => 'expense', 'source_id' => 1, 'number' => 'SUP-1', 'party' => 'Supplier', 'date' => '2026-09-01', 'base' => 3.46, 'vat' => 0.34, 'deductible_vat' => 0.34]],
            'summary' => ['outputVat' => 0, 'supportedVat' => 0.34, 'deductibleVat' => 0.34, 'nonDeductibleVat' => 0, 'result' => -0.34],
            'accountingCheck' => ['inputVat' => 0.34, 'outputVat' => 0]];
    }

    private function reconciliation(float $input = 0.34): array
    {
        return ['rows' => [['direction' => 'output', 'candidate_vat' => 0], ['direction' => 'input', 'candidate_vat' => $input]]];
    }

    private function saved(): TaxReturn
    {
        return new TaxReturn(['status' => 'draft', 'output_vat' => 0, 'input_vat' => 0.34, 'result' => -0.34,
            'snapshot' => ['version' => 'vat-settlement-v1', 'sources' => $this->books()]]);
    }

    public function test_absence_is_not_an_empty_saved_declaration(): void
    {
        $this->assertNull((new Model303SavedComparison)->make(null, $this->books(), $this->reconciliation()));
    }

    public function test_matching_saved_books_does_not_enable_filing_and_keeps_documentary_cents(): void
    {
        $result = (new Model303SavedComparison)->make($this->saved(), $this->books(), $this->reconciliation());
        $this->assertTrue($result['books_unchanged']);
        $this->assertFalse($result['ready']);
        $this->assertSame(-0.34, $result['totals'][2]['candidate']);
        $this->assertSame(0, $result['totals'][2]['difference']);
    }

    public function test_same_totals_with_identity_change_remain_changed(): void
    {
        $books = $this->books();
        $books['received'][0]['party'] = 'Corrected Supplier';
        $result = (new Model303SavedComparison)->make($this->saved(), $books, $this->reconciliation());
        $this->assertFalse($result['books_unchanged']);
        $this->assertSame(1, $result['book_changes']['counts']['modified']);
        $this->assertSame(0, $result['totals'][1]['difference']);
    }

    public function test_legacy_or_wrong_period_evidence_is_unknown_not_matching(): void
    {
        foreach ([[], ['version' => 'vat-settlement-v1', 'sources' => [...$this->books(), 'summary' => []]], ['version' => 'vat-settlement-v1', 'sources' => [...$this->books(), 'period' => ['year' => 2026, 'quarter' => 2]]]] as $snapshot) {
            $saved = $this->saved();
            $saved->snapshot = $snapshot;
            $result = (new Model303SavedComparison)->make($saved, $this->books(), $this->reconciliation());
            $this->assertFalse($result['evidence_available']);
            $this->assertFalse($result['books_unchanged']);
            $this->assertNull($result['book_changes']);
        }
    }

    public function test_prepared_or_filed_settlement_is_preserved_when_candidate_differs(): void
    {
        foreach (['prepared', 'filed'] as $status) {
            $saved = $this->saved();
            $saved->status = $status;
            $before = $saved->getAttributes();
            $result = (new Model303SavedComparison)->make($saved, $this->books(), $this->reconciliation(0.35));
            $this->assertTrue($result['protected']);
            $this->assertSame(0.01, $result['totals'][1]['difference']);
            $this->assertSame($before, $saved->getAttributes());
        }
    }
}
