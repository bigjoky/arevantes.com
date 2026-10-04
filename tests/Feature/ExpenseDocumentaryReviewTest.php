<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseDocumentaryReviewTest extends TestCase
{
    use RefreshDatabase;

    private function expense(string $number): Expense
    {
        $expense = Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'created_by' => 1,
            'number' => $number, 'type' => 'invoice', 'document_number' => $number, 'issuer_legal_name' => 'Supplier',
            'expense_date' => '2026-09-15', 'currency_code' => 'EUR', 'tax_regime' => 'standard', 'subtotal' => 100,
            'tax_amount' => 21, 'withholding_rate' => 0, 'withholding_amount' => 0, 'amount' => 121, 'paid_amount' => 0, 'status' => 'pending']);
        $expense->taxLines()->create(['kind' => 'vat', 'taxable_base' => 100, 'tax_rate' => 21,
            'tax_amount' => 21, 'deductible_percent' => 100, 'deductible_amount' => 21, 'position' => 1]);

        return $expense;
    }

    public function test_admin_sees_eligible_and_protected_historical_expenses(): void
    {
        config(['fiscal.grouped_expense_calculation' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $eligible = $this->expense('EXP-REVIEW-1');
        $paid = $this->expense('EXP-REVIEW-2');
        SupplierPayment::create(['expense_id' => $paid->id, 'created_by' => $admin->id, 'amount' => 10, 'method' => 'cash', 'paid_at' => '2026-09-15']);

        $this->actingAs($admin)->get(route('expenses.documentary-review'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('expenses/documentary-review')
                ->where('review.total', 2)
                ->where('review.data.0.number', 'EXP-REVIEW-2')
                ->where('review.data.0.eligible', false)
                ->where('review.data.1.number', 'EXP-REVIEW-1')
                ->where('review.data.1.eligible', true)
                ->where('review.data.1.edit_url', route('expenses.edit', ['expense' => $eligible, 'documentary_review' => 1])));
    }

    public function test_review_requires_enabled_feature_and_admin_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        config(['fiscal.grouped_expense_calculation' => false]);
        $this->actingAs($admin)->get(route('expenses.documentary-review'))->assertForbidden();
        config(['fiscal.grouped_expense_calculation' => true]);
        $this->actingAs(User::factory()->create(['role' => 'commercial']))
            ->get(route('expenses.documentary-review'))->assertForbidden();
    }
}
