<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseIssuerLookupTest extends TestCase
{
    use RefreshDatabase;

    private function expense(string $number, string $tradeName): Expense
    {
        return Expense::create([
            'expense_category_id' => ExpenseCategory::firstOrFail()->id,
            'number' => $number, 'type' => 'invoice', 'document_number' => $number,
            'issuer_legal_name' => 'E.S. LA PEÑITA, S.A.', 'issuer_tax_id' => 'A-12 345.678',
            'merchant_name' => $tradeName, 'expense_date' => '2026-05-29',
            'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'status' => 'paid',
        ]);
    }

    public function test_lookup_reuses_historical_expenses_without_changing_them_and_preserves_trade_names(): void
    {
        $expense = $this->expense('EXP-OLD-1', 'E.S. Universidad');
        $this->expense('EXP-OLD-2', 'E.S. Universidad');
        $this->expense('EXP-OLD-3', 'Otra estación');
        $before = $expense->fresh()->getAttributes();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson(route('expenses.issuer.lookup', ['tax_id' => 'a12345678']))->assertOk()
            ->assertJsonCount(2, 'candidates')
            ->assertJsonPath('candidates.0.trade_name', 'Otra estación')
            ->assertJsonPath('candidates.1.legal_name', 'E.S. LA PEÑITA, S.A.')
            ->assertJsonPath('candidates.1.trade_name', 'E.S. Universidad')
            ->assertJsonMissingPath('candidates.0.amount');
        $this->assertSame($before, $expense->fresh()->getAttributes());
        $this->assertSame(0, Supplier::count());
    }

    public function test_lookup_includes_active_suppliers_and_never_matches_another_tax_id(): void
    {
        Supplier::create(['legal_name' => 'Active issuer', 'tax_id' => 'B12345678', 'country' => 'ES', 'is_active' => true, 'iban' => 'PRIVATE']);
        Supplier::create(['legal_name' => 'Inactive issuer', 'tax_id' => 'B12345678', 'country' => 'ES', 'is_active' => false]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson(route('expenses.issuer.lookup', ['tax_id' => 'b-12345678']))->assertOk()
            ->assertJsonCount(1, 'candidates')->assertJsonPath('candidates.0.legal_name', 'Active issuer')
            ->assertJsonMissingPath('candidates.0.iban');
        $this->getJson(route('expenses.issuer.lookup', ['tax_id' => 'B12345679']))->assertExactJson(['candidates' => []]);
        $this->getJson(route('expenses.issuer.lookup', ['tax_id' => '%12345678']))->assertExactJson(['candidates' => []]);
    }

    public function test_lookup_is_validated_and_permission_protected(): void
    {
        $url = route('expenses.issuer.lookup', ['tax_id' => 'A12345678']);
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'technician']))->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson(route('expenses.issuer.lookup', ['tax_id' => str_repeat('A', 33)]))->assertUnprocessable()->assertJsonValidationErrors('tax_id');
    }

    public function test_changing_tax_id_format_does_not_allow_a_duplicate_invoice(): void
    {
        $this->expense('EXP-OLD-1', 'E.S. Universidad');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('expenses.store'), [
            'expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => 'invoice',
            'document_number' => 'EXP-OLD-1', 'issuer_legal_name' => 'E.S. LA PEÑITA, S.A.',
            'issuer_tax_id' => 'a12345678', 'expense_date' => '2026-05-29',
            'subtotal' => 100, 'tax_amount' => 21,
        ])->assertSessionHasErrors('document_number');
        $this->assertSame(1, Expense::count());
    }
}
