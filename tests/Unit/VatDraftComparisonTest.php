<?php

namespace Tests\Unit;

use App\Services\Tax\VatDraftComparison;
use PHPUnit\Framework\TestCase;

class VatDraftComparisonTest extends TestCase
{
    private function row(int $id, float $base = 100, float $vat = 21): array
    {
        return ['source_type' => 'expense', 'source_id' => $id, 'number' => 'EXP-'.$id,
            'party' => 'Supplier', 'tax_id' => 'B12345678', 'date' => '2026-09-15',
            'base' => $base, 'vat' => $vat, 'deductible_vat' => $vat];
    }

    public function test_groups_document_lines_and_ignores_order_changes(): void
    {
        $rows = [$this->row(1), $this->row(1, 50, 5)];
        $result = (new VatDraftComparison)->make(['received' => $rows], ['received' => array_reverse($rows)]);
        $this->assertSame(['added' => 0, 'removed' => 0, 'modified' => 0], $result['counts']);
        $this->assertSame([], $result['documents']);
    }

    public function test_detects_identity_changes_and_added_removed_documents_without_netting_them(): void
    {
        $before = ['received' => [$this->row(1), $this->row(2)], 'summary' => ['deductibleVat' => 42]];
        $changed = [...$this->row(1), 'party' => 'Corrected supplier'];
        $after = ['received' => [$changed, $this->row(3)], 'summary' => ['deductibleVat' => 42]];
        $result = (new VatDraftComparison)->make($before, $after);
        $this->assertSame(['added' => 1, 'removed' => 1, 'modified' => 1], $result['counts']);
        $this->assertSame(0.0, $result['totals']['deductibleVat']['difference']);
        $this->assertSame('Supplier', $result['documents'][0]['before']['party']);
        $this->assertSame('Corrected supplier', $result['documents'][0]['after']['party']);
    }

    public function test_bounds_details_but_preserves_complete_counts_and_negative_differences(): void
    {
        $rows = array_map(fn ($id) => $this->row($id), range(1, 60));
        $result = (new VatDraftComparison)->make(['received' => $rows, 'summary' => ['result' => -1260]], ['received' => [], 'summary' => ['result' => 0]]);
        $this->assertSame(60, $result['counts']['removed']);
        $this->assertCount(50, $result['documents']);
        $this->assertTrue($result['truncated']);
        $this->assertSame(1260.0, $result['totals']['result']['difference']);
    }
}
