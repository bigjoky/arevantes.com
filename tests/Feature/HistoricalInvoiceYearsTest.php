<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\DocumentSeries;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FiscalYear;
use App\Models\Permission;
use App\Models\TaxReturn;
use App\Models\User;
use App\Services\HistoricalInvoices\CsvReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class HistoricalInvoiceYearsTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_year_isolated_staging_dates_and_confirmations(): void
    {
        config(['fiscal.historical_invoice_2026' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $user->permissionProfile->permissions()->syncWithoutDetaching([Permission::where('key', 'manage-historical-invoices-2026')->firstOrFail()->id]);
        $supplier = EconomicFixture::supplier(['legal_name' => 'Proveedor sintético plurianual', 'tax_id' => 'B12345678', 'country' => 'ES', 'is_active' => true]);
        foreach ([2024, 2025] as $year) {
            FiscalYear::firstOrCreate(['year' => $year], ['starts_on' => "$year-01-01", 'ends_on' => "$year-12-31", 'status' => 'open']);
        }
        $postingYear = FiscalYear::where('year', 2025)->firstOrFail();
        DocumentSeries::firstOrCreate(['document_type' => 'expense', 'fiscal_year_id' => $postingYear->id], ['name' => 'Gastos sintéticos', 'prefix' => 'EXP', 'next_number' => 1, 'fiscal_year' => 2025, 'is_active' => true]);
        DocumentSeries::firstOrCreate(['document_type' => 'journal_entry', 'fiscal_year_id' => $postingYear->id], ['name' => 'Asientos sintéticos', 'prefix' => 'AST', 'next_number' => 1, 'fiscal_year' => 2025, 'is_active' => true]);
        $data = ['supplier_id' => $supplier->id, 'supplier_tax_id' => 'B12345678', 'expense_category_id' => ExpenseCategory::firstOrFail()->id, 'supplier_number' => 'HIST-2024', 'issue_date' => '2024-06-17', 'taxable_base' => '100', 'vat_rate' => '21', 'vat_amount' => '21', 'withholding_rate' => '0', 'withholding_amount' => '0', 'total' => '121'];
        $this->actingAs($user)->get('/billing/historical-invoices?year=2024')->assertOk();
        $this->post('/billing/historical-invoices', $data)->assertSessionHasErrors('issue_date');
        $data['issue_date'] = '2024-06-18';
        $this->post('/billing/historical-invoices', $data)->assertSessionHasNoErrors();
        $row = DB::table('historical_invoice_rows')->first();
        $this->assertDatabaseCount('expenses', 0);
        $this->get('/billing/historical-invoices?year=2025')->assertOk()->assertInertia(fn (Assert $p) => $p->has('rows', 0));
        $this->post('/billing/historical-invoices/'.$row->id, [...$data, 'issue_date' => '2025-01-01'])->assertSessionHasErrors('year');
        $this->post('/billing/historical-invoices/'.$row->id.'/confirm', ['accounting_date' => '2025-01-01'])->assertSessionHasErrors('year');
        $this->get('/billing/historical-invoices?year=2024')->assertOk()->assertInertia(fn (Assert $p) => $p->has('rows', 1));
        FiscalYear::where('year', 2024)->update(['status' => 'closed']);
        $this->post('/billing/historical-invoices/'.$row->id.'/confirm', ['accounting_date' => '2025-01-01'])->assertSessionHasErrors('accounting_date');
        $this->post('/billing/historical-invoices/'.$row->id.'/confirm', ['accounting_date' => '2025-01-01', 'confirm_other_year' => true])->assertSessionHasNoErrors();
        $expense = Expense::firstOrFail();
        $this->assertSame('2024-06-18', $expense->expense_date->toDateString());
        $this->assertSame('2025-01-01', $expense->accountingEntry->entry_date->toDateString());
        $this->assertSame(0, $expense->payments()->count());
        $this->post('/billing/historical-invoices/'.$row->id.'/confirm', ['accounting_date' => '2025-01-01', 'confirm_other_year' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('expenses', 1);
        $payment2024 = ['idempotency_key' => (string) \Illuminate\Support\Str::uuid(), 'paid_at' => '2025-03-20', 'amount' => '121.00', 'method' => 'cash', 'funding_source_type' => 'ledger_account', 'funding_source_id' => \App\Models\LedgerAccount::where('code', '570')->firstOrFail()->id, 'reference' => 'PAGO-SINT-2025', 'confirm_other_year' => true];
        $this->post(route('expenses.payments.store', $expense), $payment2024)->assertSessionHasNoErrors();
        $historic2024 = app(\App\Services\Billing\BillingWorkspace::class)->invoices('supplier', '2024-12-31')->where('number', $expense->number)->first();
        $this->assertSame('121.00', \App\Services\Billing\BillingWorkspace::decimal($historic2024->balance));
        $this->assertSame('2025-03-20', $expense->payments()->firstOrFail()->paid_at->toDateString());
        $this->get('/billing/historical-invoices?year=2025')->assertOk();
        $data['issue_date'] = '2025-01-16';
        $this->post('/billing/historical-invoices', $data)->assertSessionHasNoErrors();
        $duplicate = DB::table('historical_invoice_rows')->orderByDesc('id')->first();
        $this->post('/billing/historical-invoices/'.$duplicate->id.'/confirm', ['accounting_date' => '2025-02-01'])->assertSessionHasErrors('supplier_number');
        $data['supplier_number'] = 'HIST-2025';
        $this->post('/billing/historical-invoices/'.$duplicate->id, $data)->assertSessionHasNoErrors();
        $this->post('/billing/historical-invoices/'.$duplicate->id.'/confirm', ['accounting_date' => '2025-02-01'])->assertSessionHasNoErrors();
        $invoice = Expense::where('document_number', 'HIST-2025')->firstOrFail();
        $payment = ['idempotency_key' => (string) \Illuminate\Support\Str::uuid(), 'paid_at' => '2026-03-20', 'amount' => '121.00', 'method' => 'cash', 'funding_source_type' => 'ledger_account', 'funding_source_id' => \App\Models\LedgerAccount::where('code', '570')->firstOrFail()->id, 'reference' => 'PAGO-SINT-2026', 'confirm_other_year' => true];
        $this->post(route('expenses.payments.store', $invoice), $payment)->assertSessionHasNoErrors();
        $this->post(route('expenses.payments.store', $invoice), $payment)->assertSessionHasNoErrors();
        $this->assertSame(1, $invoice->payments()->count());
        $historic = app(\App\Services\Billing\BillingWorkspace::class)->invoices('supplier', '2025-12-31')->where('number', $invoice->number)->first();
        $this->assertSame('121.00', \App\Services\Billing\BillingWorkspace::decimal($historic->balance));
        $this->assertSame('2026-03-20', $invoice->payments()->firstOrFail()->paid_at->toDateString());
    }

    public function test_csv_uses_selected_year_and_preserves_rows_for_correction(): void
    {
        config(['fiscal.historical_invoice_2026' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $user->permissionProfile->permissions()->syncWithoutDetaching([Permission::where('key', 'manage-historical-invoices-2026')->firstOrFail()->id]);
        $header = implode(',', CsvReader::COLUMNS);
        foreach ([2024 => '2024-06-18', 2025 => '2025-01-01', 2026 => '2026-01-01'] as $year => $date) {
            $this->actingAs($user)->get('/billing/historical-invoices?year='.$year)->assertOk();
            $csv = $header."\nB12345678,INV-$year,$date,,,,100,21,21,0,0,121\n";
            $this->post('/billing/historical-invoices/import', ['csv' => UploadedFile::fake()->createWithContent('batch.csv', $csv)])->assertSessionHasNoErrors();
            $this->get('/billing/historical-invoices?year='.$year)->assertOk()->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.document.supplier_number', "INV-$year"));
        }
        $this->assertDatabaseCount('expenses', 0);
    }
    public function test_2025_closed_period_and_protected_return_stay_in_review(): void
    {
        config(['fiscal.historical_invoice_2026' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $user->permissionProfile->permissions()->syncWithoutDetaching([Permission::where('key', 'manage-historical-invoices-2026')->firstOrFail()->id]);
        $supplier = EconomicFixture::supplier(['legal_name' => 'Proveedor histórico bloqueado', 'tax_id' => 'B12345678', 'country' => 'ES', 'is_active' => true]);
        $year = FiscalYear::firstOrCreate(['year' => 2025], ['starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'status' => 'open']);
        $data = ['supplier_id' => $supplier->id, 'supplier_tax_id' => 'B12345678', 'expense_category_id' => ExpenseCategory::firstOrFail()->id, 'supplier_number' => 'CLOSED-2025', 'issue_date' => '2025-01-15', 'taxable_base' => '100', 'vat_rate' => '21', 'vat_amount' => '21', 'withholding_rate' => '0', 'withholding_amount' => '0', 'total' => '121'];
        $this->actingAs($user)->get('/billing/historical-invoices?year=2025')->assertOk();
        $this->post('/billing/historical-invoices', $data)->assertSessionHasNoErrors();
        $row = DB::table('historical_invoice_rows')->first();
        AccountingPeriod::updateOrCreate(['year' => 2025, 'month' => 3], ['fiscal_year_id' => $year->id, 'status' => 'closed']);
        $this->post('/billing/historical-invoices/'.$row->id.'/confirm', ['accounting_date' => '2025-03-15'])->assertSessionHasErrors();
        AccountingPeriod::where(['year' => 2025, 'month' => 3])->update(['status' => 'open']);
        TaxReturn::create(['tax_type' => 'vat', 'year' => 2025, 'quarter' => 1, 'period_from' => '2025-01-01', 'period_to' => '2025-03-31', 'status' => 'filed', 'output_vat' => 0, 'input_vat' => 0, 'result' => 0, 'snapshot' => [], 'prepared_at' => now()]);
        $this->post('/billing/historical-invoices/'.$row->id.'/confirm', ['accounting_date' => '2025-03-15'])->assertSessionHasErrors('accounting_date');
        $this->assertDatabaseHas('historical_invoice_rows', ['id' => $row->id, 'status' => 'review_required', 'expense_id' => null]);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('supplier_payments', 0);
    }

}
