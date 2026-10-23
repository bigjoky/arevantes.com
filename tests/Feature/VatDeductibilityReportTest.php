<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Tax\VatReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VatDeductibilityReportTest extends TestCase
{
    use RefreshDatabase;

    private function expense(float $percent = 50): Expense
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $expense = Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'created_by' => $user->id,
            'number' => 'EXP-DEDUCTION', 'type' => 'invoice', 'document_number' => 'TEST-1', 'issuer_legal_name' => 'Supplier',
            'expense_date' => '2026-09-15', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'status' => 'pending']);
        $expense->taxLines()->create(['kind' => 'vat', 'position' => 1, 'taxable_base' => 100, 'tax_rate' => 21,
            'tax_amount' => 21, 'deductible_percent' => $percent, 'deductible_amount' => round(21 * $percent / 100, 2)]);

        return $expense;
    }

    public static function deductions(): array
    {
        return [[0, 0], [50, 10.5], [100, 21]];
    }

    #[DataProvider('deductions')]
    public function test_documentary_vat_and_deduction_are_distinct_without_changing_the_return(float $percent, float $deduction): void
    {
        $expense = $this->expense($percent);
        app(AccountingSynchronizer::class)->syncExpense($expense, auth()->id());
        $before = $expense->fresh()->toArray();
        $entries = JournalEntry::count();
        $report = app(VatReport::class)->make(2026, 3);
        $row = $report['received']->sole();
        $this->assertEquals(21, $row['vat']);
        $this->assertEquals($deduction, $row['deductible_vat']);
        $this->assertEquals(21 - $deduction, $row['non_deductible_vat']);
        $this->assertEquals(121, $row['total']);
        $this->assertEquals(21, $report['summary']['supportedVat']);
        $this->assertEquals($deduction, $report['summary']['deductibleVat']);
        $this->assertEquals(-$deduction, $report['summary']['result']);
        $this->assertEquals($deduction, $report['rates']->sole()['inputVat']);
        $this->assertTrue($report['accountingCheck']['matches']);
        $this->assertSame($entries, JournalEntry::count());
        $this->assertSame($before, $expense->fresh()->toArray());
    }

    public function test_received_csv_separates_documentary_deductible_and_non_deductible_quotas(): void
    {
        $this->expense();
        $response = $this->get(route('tax.export', ['year' => 2026, 'quarter' => 3, 'book' => 'received']))->assertOk();
        $lines = explode("\n", trim(substr($response->streamedContent(), 3)));
        $headers = str_getcsv($lines[0], ';', '"', '');
        $row = array_combine($headers, str_getcsv($lines[1], ';', '"', ''));
        $this->assertSame('21,00', $row['supported_vat']);
        $this->assertSame('10,50', $row['deductible_vat']);
        $this->assertSame('10,50', $row['non_deductible_vat']);
        $this->assertSame('121,00', $row['base_plus_vat']);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_surcharge_does_not_duplicate_vat_base_or_become_deductible_vat(): void
    {
        $expense = $this->expense();
        $expense->update(['tax_amount' => 26.2, 'amount' => 126.2]);
        $expense->taxLines()->create(['kind' => 'equivalence_surcharge', 'position' => 2, 'taxable_base' => 100,
            'tax_rate' => 5.2, 'tax_amount' => 5.2, 'deductible_percent' => 0, 'deductible_amount' => 0]);
        app(AccountingSynchronizer::class)->syncExpense($expense, auth()->id());
        $report = app(VatReport::class)->make(2026, 3);
        $this->assertCount(1, $report['received']);
        $this->assertEquals(100, $report['summary']['taxableBaseReceived']);
        $this->assertEquals(21, $report['summary']['supportedVat']);
        $this->assertEquals(10.5, $report['summary']['nonDeductibleVat']);
        $this->assertTrue($report['accountingCheck']['matches']);
    }

    public function test_legacy_expense_keeps_previous_deduction_without_rewriting_history(): void
    {
        $expense = $this->expense();
        $expense->taxLines()->delete();
        $row = app(VatReport::class)->make(2026, 3)['received']->sole();
        $this->assertEquals(21, $row['vat']);
        $this->assertEquals(21, $row['deductible_vat']);
        $this->assertEquals(0, $row['non_deductible_vat']);
        $this->assertSame(0, $expense->taxLines()->count());
    }
}
