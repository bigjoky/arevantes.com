<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpenseVatRoundingTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $quota): array
    {
        return [
            'expense_category_id' => ExpenseCategory::firstOrFail()->id,
            'type' => 'invoice', 'document_number' => 'VAT-ROUNDING',
            'issuer_legal_name' => 'Supplier test', 'issuer_tax_id' => 'B12345678',
            'expense_date' => '2026-07-13', 'tax_lines' => [
                ['kind' => 'vat', 'taxable_base' => 100, 'tax_rate' => 21, 'tax_amount' => $quota, 'deductible_percent' => 50],
            ],
        ];
    }

    public static function acceptedQuotas(): array
    {
        return [['21.02', '121.02', '10.51'], ['20.98', '120.98', '10.49'], ['21.00', '121.00', '10.50']];
    }

    #[DataProvider('acceptedQuotas')]
    public function test_supplier_quota_is_preserved_and_posted_balanced(string $quota, string $total, string $deductible): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('expenses.store'), $this->payload($quota))->assertRedirect()->assertSessionHasNoErrors();
        $expense = Expense::sole();
        $this->assertSame($quota, $expense->tax_amount);
        $this->assertSame($total, $expense->amount);
        $this->assertSame($quota, $expense->taxLines()->sole()->tax_amount);
        $this->assertSame($deductible, $expense->taxLines()->sole()->deductible_amount);
        $lines = $expense->accountingEntry->lines;
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.001);
    }

    public static function invalidQuotas(): array
    {
        return [['21.03'], ['20.97'], ['21.001'], ['-0.01']];
    }

    #[DataProvider('invalidQuotas')]
    public function test_invalid_quota_cannot_create_an_expense(string $quota): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('expenses.store'), $this->payload($quota))->assertSessionHasErrors('tax_lines.0.tax_amount');
        $this->assertSame(0, Expense::count());
    }
}
