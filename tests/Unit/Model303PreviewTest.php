<?php

namespace Tests\Unit;

use App\Services\Tax\Model303Preview;
use PHPUnit\Framework\TestCase;

class Model303PreviewTest extends TestCase
{
    private function row(int $id, float $rate = 21, float $base = 100, float $vat = 21): array
    {
        return ['source_type' => 'sales_document', 'source_id' => $id, 'rate' => $rate, 'base' => $base, 'vat' => $vat];
    }

    private function report(array $issued = [], array $received = []): array
    {
        return ['period' => ['year' => 2026, 'quarter' => 3], 'issued' => $issued, 'received' => $received];
    }

    public function test_preserves_documentary_rounding_in_official_candidate_boxes(): void
    {
        $result = (new Model303Preview)->make($this->report([$this->row(1, 10, 3.46, 0.34), $this->row(2, 10, 3.46, 0.34)]), array_fill_keys([1, 2], ['type' => 'invoice', 'country' => 'ES']));
        $this->assertSame(6.92, $result['groups'][1]['base']);
        $this->assertSame(0.68, $result['groups'][1]['vat']);
        $this->assertSame('04', $result['groups'][1]['base_box']);
        $this->assertSame('06', $result['groups'][1]['vat_box']);
        $this->assertFalse($result['ready']);
    }

    public function test_groups_rectifications_with_sign_without_rate_breakdown(): void
    {
        $result = (new Model303Preview)->make($this->report([$this->row(1, 21, -100, -21), $this->row(1, 10, -50, -5)]), [1 => ['type' => 'credit_note', 'country' => 'ES']]);
        $this->assertEquals(-150, $result['groups'][3]['base']);
        $this->assertEquals(-26, $result['groups'][3]['vat']);
        $this->assertNull($result['groups'][3]['rate']);
        $this->assertNull($result['groups'][2]['vat']);
        $this->assertCount(2, $result['groups'][3]['documents']);
    }

    public function test_does_not_invent_exemptions_geography_or_deduction_classification(): void
    {
        $rows = [$this->row(1, 0, 100, 0), $this->row(2), $this->row(3), $this->row(4, 7)];
        $received = [['source_type' => 'expense', 'source_id' => 7, 'deductible_vat' => 0.34]];
        $result = (new Model303Preview)->make($this->report($rows, $received), [1 => ['type' => 'invoice', 'country' => 'ES'], 2 => ['type' => 'invoice', 'country' => 'FR'], 4 => ['type' => 'invoice', 'country' => 'ES']]);
        $this->assertSame($rows, $result['pendingIssued']);
        $this->assertSame($received, $result['pendingReceived']);
        foreach ($result['groups'] as $group) {
            $this->assertNull($group['base']);
            $this->assertNull($group['vat']);
        }
    }

    public function test_requires_a_validated_period_specific_design(): void
    {
        foreach ([[2027, 3], [2026, 1], [2025, 4], [2026, null]] as [$year, $quarter]) {
            $report = $this->report([$this->row(1)]);
            $report['period'] = ['year' => $year, 'quarter' => $quarter];
            $result = (new Model303Preview)->make($report, [1 => ['type' => 'invoice', 'country' => 'ES']]);
            $this->assertFalse($result['supported']);
            $this->assertNull($result['groups'][2]['vat']);
            $this->assertCount(1, $result['pendingIssued']);
        }
    }

    public function test_deductions_need_current_explicit_review_and_keep_unprorated_bases(): void
    {
        $row = ['source_type' => 'expense', 'source_id' => 1, 'base' => 100, 'vat' => 21, 'deductible_vat' => 10.5];
        $report = $this->report([], [$row, [...$row, 'source_id' => 2], [...$row, 'source_type' => 'purchase_document'], [...$row, 'source_id' => 3, 'deductible_vat' => 0]]);
        $classifications = ['expense:1' => ['current' => true, 'operation_class' => 'domestic_current', 'registered_on' => '2026-07-01', 'deduction_year' => 2026, 'deduction_quarter' => 3],
            'expense:2' => ['current' => false, 'operation_class' => 'domestic_current'],
            'expense:3' => ['current' => true, 'operation_class' => 'domestic_current']];
        $result = (new Model303Preview)->make($report, [], $classifications);
        $this->assertEquals(100, $result['deductions'][0]['base']);
        $this->assertEquals(10.5, $result['deductions'][0]['vat']);
        $this->assertCount(1, $result['deductions'][0]['documents']);
        $this->assertCount(3, $result['pendingReceived']);
        $this->assertNull($result['deductions'][1]['base']);
        $this->assertFalse($result['ready']);
    }

    public function test_purchase_diagnostics_remain_pending_without_current_review(): void
    {
        $row = ['source_type' => 'purchase_document', 'source_id' => 1, 'base' => 3.46, 'vat' => 0.34, 'deductible_vat' => 0.34];
        $service = new Model303Preview;
        $result = $service->make($this->report([], [$row]), [], ['purchase_document:1' => [
            'current' => false, 'operation_class' => 'domestic_current', 'issues' => ['purchase_shipping_pending'],
        ]]);
        $this->assertSame(['purchase_shipping_pending'], $result['pendingReceived'][0]['fiscal_issues']);
        $this->assertSame(0.34, $result['pendingReceived'][0]['vat']);
        $this->assertNull($result['deductions'][0]['vat']);
        $missing = $service->make($this->report([], [$row]), []);
        $this->assertSame(['purchase_review_pending'], $missing['pendingReceived'][0]['fiscal_issues']);
    }
}
