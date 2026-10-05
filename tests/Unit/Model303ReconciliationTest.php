<?php

namespace Tests\Unit;

use App\Services\Tax\Model303Reconciliation;
use PHPUnit\Framework\TestCase;

class Model303ReconciliationTest extends TestCase
{
    private function row(int $id, float $quota): array
    {
        return ['source_type' => 'expense', 'source_id' => $id, 'number' => 'EXP-'.$id, 'vat' => $quota, 'deductible_vat' => $quota];
    }

    private function preview(array $candidates = [], array $pending = [], array $deferred = []): array
    {
        return ['supported' => true, 'groups' => [], 'deductions' => [['documents' => $candidates]],
            'pendingIssued' => [], 'pendingReceived' => $pending, 'deferredReceived' => $deferred];
    }

    private function books(array $rows, float $quota): array
    {
        return ['issued' => [], 'received' => $rows, 'summary' => ['outputVat' => 0, 'deductibleVat' => $quota],
            'accountingCheck' => ['outputVat' => 0, 'inputVat' => $quota]];
    }

    public function test_equal_amounts_do_not_enable_filing(): void
    {
        $rows = [$this->row(1, 0.34), $this->row(1, 0.34)];
        $result = (new Model303Reconciliation)->make($this->books($rows, 0.68), $this->preview($rows));
        $this->assertTrue($result['matches']);
        $this->assertFalse($result['ready']);
        $this->assertSame(0.68, $result['rows'][1]['candidate_vat']);
        $this->assertSame([], $result['differences']);
    }

    public function test_explains_deferral_additions_and_pending_without_canceling_them_out(): void
    {
        $deferred = $this->row(1, 10.5);
        $pending = $this->row(2, 21);
        $added = $this->row(3, 10.5);
        $result = (new Model303Reconciliation)->make($this->books([$deferred, $pending], 31.5), $this->preview([$added], [$pending], [$deferred]));
        $this->assertSame(['deferred_period', 'pending_review', 'added_period'], array_column($result['differences'], 'reason'));
        $this->assertSame(-21, $result['rows'][1]['book_difference']);
        $this->assertSame(0, $result['rows'][1]['unexplained_difference']);
        $this->assertFalse($result['matches']);
    }

    public function test_summary_row_discrepancy_is_not_silently_explained(): void
    {
        $row = $this->row(1, 0.34);
        $result = (new Model303Reconciliation)->make($this->books([$row], 0.35), $this->preview([$row]));
        $this->assertSame(-0.01, $result['rows'][1]['unexplained_difference']);
        $this->assertFalse($result['matches']);
    }

    public function test_missing_totals_and_zero_quota_pending_do_not_look_reconciled(): void
    {
        $row = $this->row(1, 0);
        $result = (new Model303Reconciliation)->make(['issued' => [], 'received' => [$row]], $this->preview([], [$row]));
        $this->assertNull($result['rows'][1]['ledger_difference']);
        $this->assertNull($result['rows'][1]['unexplained_difference']);
        $this->assertSame(1, $result['pending_rows']);
        $this->assertFalse($result['matches']);
    }
}
