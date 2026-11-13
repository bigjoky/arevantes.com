<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class SelectedFiscalYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_year_persists_and_filters_supplier_documents(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        foreach ([2024, 2025, 2026] as $year) {
            EconomicFixture::expense(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => 'invoice', 'number' => 'YEAR-'.$year,
                'issuer_legal_name' => 'Synthetic supplier', 'expense_date' => $year.'-01-15', 'currency_code' => 'EUR',
                'subtotal' => '100', 'tax_amount' => '21', 'amount' => '121', 'paid_amount' => '0', 'status' => 'pending']);
        }
        $this->actingAs($user)->get('/billing/suppliers?year=2025')->assertOk()->assertInertia(fn (Assert $page) => $page->where('workspaceContext.currentYear', 2025)->where('invoices.total', 1)->where('invoices.data.0.number', 'YEAR-2025'));
        $this->get('/billing/suppliers')->assertOk()->assertInertia(fn (Assert $page) => $page->where('workspaceContext.currentYear', 2025)->where('invoices.total', 1)->where('invoices.data.0.number', 'YEAR-2025'));
        $this->get('/billing/suppliers?year=2026')->assertOk()->assertInertia(fn (Assert $page) => $page->where('invoices.total', 1)->where('invoices.data.0.number', 'YEAR-2026'));
    }

    public function test_regularization_rejects_mutations_outside_2026(): void
    {
        config(['fiscal.historical_invoice_2026' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $user->permissionProfile->permissions()->syncWithoutDetaching([Permission::where('key', 'manage-historical-invoices-2026')->firstOrFail()->id]);
        $this->actingAs($user)->get('/billing/historical-invoices?year=2025')->assertOk()->assertInertia(fn (Assert $page) => $page->where('selectedYear', 2025));
        $this->post('/billing/historical-invoices', [])->assertSessionHasErrors('year');
        $this->post('/billing/historical-invoices/import', [])->assertSessionHasErrors('year');
        $this->post('/billing/historical-invoices/1/confirm', ['accounting_date' => '2026-03-01'])->assertSessionHasErrors('year');
        $this->assertSame(0, DB::table('historical_invoice_rows')->count());
        $this->get('/billing/historical-invoices?year=2024')->assertOk()->assertInertia(fn (Assert $page) => $page->where('selectedYear', 2024));
        $this->post('/billing/historical-invoices/1/confirm', ['accounting_date' => '2026-03-01'])->assertSessionHasErrors('year');
        $this->get('/billing/historical-invoices?year=2026')->assertOk()->assertInertia(fn (Assert $page) => $page->where('selectedYear', 2026));
        $this->post('/billing/historical-invoices/1/confirm', ['accounting_date' => '2027-03-01'])->assertSessionHasErrors('accounting_date');
    }
    public function test_cross_year_payments_follow_payment_date_not_invoice_date(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2028-01-02'));
        $user = User::factory()->create(['role' => 'admin']);
        foreach ([[2025, 2026], [2026, 2027]] as [$invoiceYear, $paymentYear]) {
            $expense = EconomicFixture::expense(['expense_category_id' => ExpenseCategory::firstOrFail()->id,
                'type' => 'invoice', 'number' => 'CROSS-'.$invoiceYear, 'issuer_legal_name' => 'Synthetic supplier',
                'expense_date' => $invoiceYear.'-12-15', 'currency_code' => 'EUR', 'subtotal' => '100', 'tax_amount' => '21',
                'amount' => '121', 'paid_amount' => '121', 'status' => 'paid']);
            \App\Models\SupplierPayment::create(['expense_id' => $expense->id, 'paid_at' => $paymentYear.'-01-15', 'amount' => '121', 'method' => 'cash']);
        }
        $this->actingAs($user)->get('/billing/suppliers?year=2025')->assertOk()->assertInertia(fn (Assert $page) => $page->where('invoices.total', 1)->where('invoices.data.0.number', 'CROSS-2025')->where('invoices.data.0.balance', '121.00'));
        $this->get('/billing/suppliers?year=2026')->assertOk()->assertInertia(fn (Assert $page) => $page->where('invoices.total', 1)->where('invoices.data.0.number', 'CROSS-2026')->where('invoices.data.0.balance', '121.00'));
        $this->get('/billing/payments?year=2026&tab=pending')->assertOk()->assertInertia(fn (Assert $page) => $page->where('pending.total', 1)->where('pending.data.0.number', 'CROSS-2026'));
        $this->get('/billing/payments?year=2026')->assertOk()->assertInertia(fn (Assert $page) => $page->where('movements.total', 1)->where('movements.data.0.number', 'CROSS-2025'));
        $this->get('/billing/payments?year=2027')->assertOk()->assertInertia(fn (Assert $page) => $page->where('movements.total', 1)->where('movements.data.0.number', 'CROSS-2026'));
    }

    public function test_ordinary_operations_require_explicit_confirmation_for_another_year(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->get('/billing/suppliers?year=2026')->assertOk();
        $expense = EconomicFixture::expense(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'type' => 'invoice', 'number' => 'GUARD-2026', 'expense_date' => '2026-01-01', 'currency_code' => 'EUR', 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'status' => 'pending']);
        $sale = \App\Models\SalesDocument::create(['number' => 'GUARD-SALE-2026', 'type' => 'invoice', 'status' => 'issued', 'issue_date' => '2026-01-01', 'customer_snapshot' => ['company_name' => 'Synthetic'], 'subtotal' => 100, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0, 'locked_at' => now()]);
        foreach ([['/expenses', 'expense_date'], ['/purchases', 'issue_date'], ['/sales', 'issue_date'],
            ['/expenses/'.$expense->id.'/payments', 'paid_at'], ['/sales/'.$sale->id.'/payments', 'paid_at'], ['/accounting/entries', 'entry_date']] as [$url, $field]) {
            $response = $this->post($url, [$field => '2025-12-15']);
            $this->assertTrue(session()->has('errors'), 'No se bloqueó '.$url.' (HTTP '.$response->getStatusCode().').');
            $response->assertSessionHasErrors($field);
        }
        // Confirmation removes this guard; normal endpoint validation still applies.
        $response = $this->post('/expenses', ['expense_date' => '2025-12-15', 'confirm_other_year' => true]);
        $response->assertSessionHasErrors('expense_category_id');
        $this->assertStringNotContainsString('Confirma expresamente', session('errors')->first('expense_date'));
    }

}
