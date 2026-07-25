<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\TaxReturn;
use App\Models\Unit;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Tax\VatReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_transport_is_reported_as_a_separate_21_percent_vat_base(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'VAT Transport SL', 'country' => 'ES']);
        $product = Product::create(['type' => 'product', 'sku' => 'VAT-TRANSPORT', 'name' => 'Equipment', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 150, 'cost_price' => 100, 'is_active' => true]);
        $invoice = PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'created_by' => $user->id, 'number' => 'FR-VAT-TRANSPORT', 'status' => 'registered', 'issue_date' => '2026-07-13', 'subtotal' => 120, 'shipping_cost' => 20, 'tax_amount' => 14.20, 'amount' => 134.20]);
        $invoice->lines()->create(['product_id' => $product->id, 'position' => 1, 'description' => 'Equipment', 'quantity' => 1, 'unit_price' => 100, 'discount_percent' => 0, 'tax_percent' => 10, 'subtotal' => 100, 'tax_amount' => 10, 'total' => 110]);
        app(AccountingSynchronizer::class)->syncPurchaseDocument($invoice, $user->id);

        $report = app(VatReport::class)->make(2026, 3);

        $this->assertEqualsWithDelta(120, $report['summary']['taxableBaseReceived'], 0.001);
        $this->assertEqualsWithDelta(14.20, $report['summary']['deductibleVat'], 0.001);
        $this->assertEqualsWithDelta(20, $report['rates']->firstWhere('rate', 21)['receivedBase'], 0.001);
        $this->assertTrue($report['accountingCheck']['matches']);
    }

    public function test_quarterly_vat_registers_match_accounting_and_export_to_csv(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $invoice = SalesDocument::create(['type' => 'invoice', 'number' => 'FAC-TAX-1', 'status' => 'issued', 'issue_date' => '2026-07-10', 'issued_at' => now(), 'locked_at' => now(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0, 'customer_snapshot' => ['company_name' => 'Solar Client SL', 'tax_id' => 'B12345678']]);
        $invoice->lines()->create(['position' => 1, 'description' => 'Installation', 'quantity' => 1, 'unit_price' => 100, 'discount_percent' => 0, 'tax_percent' => 21, 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'total' => 121]);
        $category = ExpenseCategory::create(['code' => 'SERV', 'name' => 'Services', 'is_active' => true]);
        Expense::create(['expense_category_id' => $category->id, 'created_by' => $user->id, 'number' => 'EXP-TAX-1', 'type' => 'invoice', 'document_number' => 'EXT-55', 'merchant_name' => 'Gestoria', 'expense_date' => '2026-08-01', 'subtotal' => 50, 'tax_amount' => 10.5, 'amount' => 60.5, 'paid_amount' => 0, 'status' => 'pending']);
        app(AccountingSynchronizer::class)->sync($user->id);

        $this->actingAs($user)->get(route('tax.index', ['year' => 2026, 'quarter' => 3]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('tax/index')->has('issued', 1)->has('received', 1)
            ->where('summary.outputVat', 21)->where('summary.deductibleVat', 10.5)->where('summary.result', 10.5)
            ->where('accountingCheck.matches', true));
        $this->get(route('tax.export', ['year' => 2026, 'quarter' => 3, 'book' => 'issued']))->assertOk()->assertDownload('vat-book-issued-2026-Q3.csv');

        $this->post(route('tax.returns.prepare'), ['year' => 2026, 'quarter' => 3])->assertRedirect()->assertSessionHasNoErrors();
        $return = TaxReturn::firstOrFail();
        $this->assertSame('prepared', $return->status);
        $this->assertSame('10.50', $return->result);
        $this->assertNotNull($return->journal_entry_id);
        $this->assertDatabaseHas('journal_entries', ['id' => $return->journal_entry_id, 'entry_kind' => 'tax_settlement']);
        $this->get(route('tax.index', ['year' => 2026, 'quarter' => 3]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('accountingCheck.matches', true)->where('taxReturn.status', 'prepared'));

        $this->post(route('tax.returns.file', $return), ['filing_reference' => 'AEAT-CSV-123', 'filed_on' => '2026-10-15', 'receipt' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('filed', $return->fresh()->status);
        $this->get(route('tax.returns.receipt', $return))->assertOk()->assertDownload('vat-2026-Q3-receipt.pdf');

        $this->post(route('tax.returns.payments.store', $return), ['paid_on' => '2026-10-16', 'amount' => 10.5, 'method' => 'direct_debit', 'reference' => 'NRC-IVA-Q3'])->assertRedirect()->assertSessionHasNoErrors();
        $return->refresh();
        $this->assertSame('settled', $return->settlement_status);
        $this->assertSame('10.50', $return->paid_amount);
        $this->assertDatabaseHas('journal_entries', ['entry_kind' => 'tax_payment']);

        $account = BankAccount::create(['name' => 'Taxes bank', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $csv = "Fecha;Importe;Concepto;Referencia\n16/10/2026;-10,50;AEAT MODELO 303;NRC-IVA-Q3\n";
        $this->post(route('banking.import', $account), ['statement' => UploadedFile::fake()->createWithContent('tax.csv', $csv)])->assertRedirect();
        $this->post(route('banking.auto', $account))->assertRedirect();
        $this->assertSame(1, BankReconciliation::whereNotNull('tax_return_payment_id')->count());
    }
}
