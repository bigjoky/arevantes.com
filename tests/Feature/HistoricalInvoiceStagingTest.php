<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HistoricalInvoices\StageInvoices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoricalInvoiceStagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_preparation_persists_rows_without_creating_economic_effects(): void
    {
        $user = User::factory()->create();
        $values = [
            'supplier_tax_id' => 'B12345678', 'supplier_number' => 'SYN-42', 'issue_date' => '2026-01-02',
            'operation_date' => '', 'received_date' => '', 'due_date' => '', 'taxable_base' => '100',
            'vat_rate' => '21', 'vat_amount' => '21', 'withholding_rate' => '0', 'withholding_amount' => '0', 'total' => '121',
        ];
        $before = [];
        foreach (['expenses', 'purchase_documents', 'journal_entries', 'supplier_payments'] as $table) {
            $before[$table] = DB::table($table)->count();
        }
        $id = app(StageInvoices::class)->fromCsv([
            ['row' => 2, 'values' => $values, 'errors' => []],
            ['row' => 3, 'values' => [...$values, 'issue_date' => '2025-12-31'], 'errors' => []],
        ], $user->id);
        $rows = DB::table('historical_invoice_rows')->where('batch_id', $id)->orderBy('row_number')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('2026-01-02', json_decode($rows[0]->document, true)['issue_date']);
        $this->assertSame('review_required', $rows[0]->status);
        $this->assertNotEmpty(json_decode($rows[1]->errors, true));
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count());
        }
    }
}
