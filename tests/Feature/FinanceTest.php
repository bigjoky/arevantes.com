<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_dashboard_aggregates_cash_vat_and_outstanding_balances(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Supplier SL', 'country' => 'ES', 'payment_terms_days' => 30]);
        $sale = SalesDocument::create(['type' => 'invoice', 'number' => 'FAC-2026-00001', 'status' => 'partial', 'issue_date' => '2026-07-01', 'due_date' => '2026-07-31', 'issued_at' => now(), 'locked_at' => now(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 60, 'customer_snapshot' => ['first_name' => 'Ana']]);
        $sale->payments()->create(['created_by' => $user->id, 'paid_at' => '2026-07-10', 'amount' => 60, 'method' => 'bank_transfer']);
        $purchase = PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'number' => 'FR-2026-00001', 'status' => 'partial', 'issue_date' => '2026-07-02', 'due_date' => '2026-08-01', 'subtotal' => 50, 'tax_amount' => 10, 'amount' => 60, 'paid_amount' => 20]);
        SupplierPayment::create(['purchase_document_id' => $purchase->id, 'created_by' => $user->id, 'paid_at' => '2026-07-11', 'amount' => 20, 'method' => 'card']);
        Expense::create(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'created_by' => $user->id, 'number' => 'EXP-2026-00001', 'type' => 'receipt', 'expense_date' => '2026-07-03', 'subtotal' => 25, 'tax_amount' => 5, 'amount' => 30, 'status' => 'pending']);

        $this->actingAs($user)->get(route('finance.index', ['year' => 2026]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('finance/index')
            ->where('summary.cashIn', 60)
            ->where('summary.cashOut', 20)
            ->where('summary.receivable', 61)
            ->where('summary.payable', 70)
            ->where('vat.output', 21)
            ->where('vat.input', 15)
            ->has('cashFlow', 12));

        $this->actingAs($user)->get(route('finance.export', ['type' => 'cash', 'year' => 2026]))->assertOk()->assertDownload('finance-cash-2026.csv');
        $this->actingAs($user)->get(route('finance.export', ['type' => 'vat', 'year' => 2026]))->assertOk()->assertDownload('finance-vat-2026.csv');
    }

    public function test_financial_dashboard_defaults_to_the_current_year(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);

        $this->actingAs($user)->get(route('finance.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('finance/index')
            ->where('year', now()->year));
    }
}
