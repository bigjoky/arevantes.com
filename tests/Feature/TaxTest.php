<?php

namespace Tests\Feature;

use App\Http\Controllers\TaxController;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SalesDocument;
use App\Models\TaxRate;
use App\Models\TaxReturn;
use App\Models\Unit;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\JournalPoster;
use App\Services\Banking\ControlledReconciliation;
use App\Services\Tax\VatReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class TaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_tax_payment_rechecks_remaining_balance_without_creating_entries(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        foreach ([100, -100] as $index => $result) {
            $stale = TaxReturn::create([
                'year' => 2026, 'quarter' => $index + 1, 'period_from' => '2026-01-01', 'period_to' => '2026-03-31',
                'status' => 'filed', 'result' => $result, 'result_option' => $result > 0 ? 'payable' : 'refund',
                'output_vat' => 100, 'input_vat' => 0, 'snapshot' => [], 'prepared_at' => now(), 'paid_amount' => 0,
            ]);
            TaxReturn::whereKey($stale->id)->update(['paid_amount' => 80, 'settlement_status' => 'partial']);
            $before = $stale->fresh()->getAttributes();
            $entries = JournalEntry::count();
            $request = Request::create('/', 'POST', ['paid_on' => '2026-04-20', 'amount' => 30, 'method' => 'bank_transfer']);
            $request->setUserResolver(fn () => $user);
            try {
                app(TaxController::class)->pay($request, $stale, app(JournalPoster::class));
                $this->fail('A stale payment must not exceed the remaining balance.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('amount', $exception->errors());
            }
            $this->assertSame($before, $stale->fresh()->getAttributes());
            $this->assertSame(0, $stale->payments()->count());
            $this->assertSame($entries, JournalEntry::count());
        }
    }

    public function test_vat_books_have_independent_routes_and_preserve_the_requested_period(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        foreach (['summary' => '/tax', 'issued' => '/tax/issued', 'received' => '/tax/received'] as $section => $url) {
            $this->actingAs($user)->get($url.'?year=2025&quarter=2')->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('tax/index')
                    ->where('initialSection', $section)->where('period.year', 2025)->where('period.quarter', 2)
                    ->where('canManageTax', false));
            $this->actingAs(User::factory()->create(['role' => 'technician']))->get($url)->assertForbidden();
        }
    }

    public function test_tax_action_capability_is_resolved_by_the_permission_gate(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        Gate::define('manage-tax', fn (User $actor) => $actor->id === $user->id);
        $this->actingAs($user)->get('/tax?year=2026&quarter=1')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManageTax', true));
    }

    public function test_purchase_vat_rows_preserve_supplier_identity_including_shipping_and_unallocated_amounts(): void
    {
        $supplier = EconomicFixture::supplier(['legal_name' => 'Original supplier', 'tax_id' => 'B12345678', 'country' => 'ES']);
        $document = EconomicFixture::legacy(fn () => EconomicFixture::purchase([
            'type' => 'supplier_invoice', 'supplier_id' => $supplier->id,
            'number' => 'HISTORICAL-TAX', 'status' => 'registered', 'issue_date' => '2026-07-13',
            'subtotal' => 130, 'shipping_cost' => 20, 'tax_amount' => 17.30, 'amount' => 147.30,
        ]));
        $product = Product::create(['type' => 'product', 'sku' => 'HISTORICAL-TAX', 'name' => 'Equipment', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 150, 'cost_price' => 100, 'is_active' => true]);
        $document->lines()->create([
            'product_id' => $product->id, 'position' => 1, 'description' => 'Equipment', 'quantity' => 1, 'unit_price' => 100,
            'discount_percent' => 0, 'tax_percent' => 10, 'subtotal' => 100, 'tax_amount' => 10, 'total' => 110,
        ]);
        $supplier->update(['legal_name' => 'Changed supplier', 'tax_id' => 'B87654321']);

        $rows = app(VatReport::class)->make(2026, 3)['received'];
        $this->assertCount(3, $rows);
        foreach ($rows as $row) {
            $this->assertSame('Original supplier', $row['party']);
            $this->assertSame('B12345678', $row['tax_id']);
        }

        $document->update(['supplier_snapshot' => ['legal_name' => 'Historical without NIF', 'tax_id' => null]]);
        $this->assertNull(app(VatReport::class)->make(2026, 3)['received']->first()['tax_id']);

        $document->update(['supplier_snapshot' => null]);
        $legacyRow = app(VatReport::class)->make(2026, 3)['received']->first();
        $this->assertSame('Changed supplier', $legacyRow['party']);
        $this->assertSame('B87654321', $legacyRow['tax_id']);
    }

    public function test_sales_credit_note_reduces_vat_and_posts_a_balanced_entry(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $credit = SalesDocument::create([
            'type' => 'credit_note', 'number' => 'AB-2026-00001', 'status' => 'issued',
            'issue_date' => '2026-07-15', 'issued_at' => now(), 'locked_at' => now(),
            'subtotal' => -100, 'discount_amount' => 0, 'tax_amount' => -21,
            'amount' => -121, 'paid_amount' => 0, 'customer_snapshot' => ['company_name' => 'Cliente SL'],
        ]);
        $credit->lines()->create([
            'position' => 1, 'description' => 'Correction', 'quantity' => -1,
            'unit_price' => 100, 'discount_percent' => 0, 'tax_percent' => 21,
            'subtotal' => -100, 'discount_amount' => 0, 'tax_amount' => -21, 'total' => -121,
        ]);

        $created = app(AccountingSynchronizer::class)->syncSalesDocument($credit, $user->id);
        $this->assertSame(1, $created);
        $entry = $credit->accountingEntry()->with('lines.account')->firstOrFail();
        $report = app(VatReport::class)->make(2026, 3);

        $this->assertEqualsWithDelta(121, $entry->lines->sum('debit'), 0.001);
        $this->assertEqualsWithDelta(121, $entry->lines->sum('credit'), 0.001);
        $this->assertTrue($entry->lines->every(fn ($line) => (float) $line->debit >= 0 && (float) $line->credit >= 0));
        $this->assertEqualsWithDelta(100, $entry->lines->firstWhere('account.code', '700')->debit, 0.001);
        $this->assertEqualsWithDelta(21, $entry->lines->firstWhere('account.code', '477')->debit, 0.001);
        $this->assertEqualsWithDelta(-100, $report['summary']['taxableBaseIssued'], 0.001);
        $this->assertEqualsWithDelta(-21, $report['summary']['outputVat'], 0.001);
        $this->assertEqualsWithDelta(-21, $report['summary']['result'], 0.001);
        $this->assertTrue($report['accountingCheck']['matches']);
    }

    public function test_supplier_transport_is_reported_as_a_separate_21_percent_vat_base(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = EconomicFixture::supplier(['legal_name' => 'VAT Transport SL', 'country' => 'ES']);
        $product = Product::create(['type' => 'product', 'sku' => 'VAT-TRANSPORT', 'name' => 'Equipment', 'unit_id' => Unit::firstOrFail()->id, 'tax_rate_id' => TaxRate::firstOrFail()->id, 'sale_price' => 150, 'cost_price' => 100, 'is_active' => true]);
        $invoice = EconomicFixture::purchase(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'created_by' => $user->id, 'number' => 'FR-VAT-TRANSPORT', 'status' => 'registered', 'issue_date' => '2026-07-13', 'subtotal' => 120, 'shipping_cost' => 20, 'tax_amount' => 14.20, 'amount' => 134.20]);
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
        EconomicFixture::expense(['expense_category_id' => $category->id, 'created_by' => $user->id, 'number' => 'EXP-TAX-1', 'type' => 'invoice', 'document_number' => 'EXT-55', 'merchant_name' => 'Gestoria', 'expense_date' => '2026-08-01', 'subtotal' => 50, 'tax_amount' => 10.5, 'amount' => 60.5, 'paid_amount' => 0, 'status' => 'pending']);
        app(AccountingSynchronizer::class)->sync($user->id);

        $this->actingAs($user)->get(route('tax.index', ['year' => 2026, 'quarter' => 3]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('tax/index')->has('issued', 1)->has('received', 1)
            ->where('summary.outputVat', 21)->where('summary.deductibleVat', 10.5)->where('summary.result', 10.5)
            ->where('accountingCheck.matches', true));
        $this->get(route('tax.export', ['year' => 2026, 'quarter' => 3, 'book' => 'issued']))->assertOk()->assertDownload('vat-book-issued-2026-Q3.csv');

        $this->post(route('tax.returns.prepare'), ['year' => 2026, 'quarter' => 3])->assertRedirect()->assertSessionHasNoErrors();
        $return = TaxReturn::firstOrFail();
        $this->assertSame('draft', $return->status);
        $this->post(route('tax.returns.review', $return), ['reason' => 'Reviewed registers'])->assertSessionHasNoErrors();
        $this->post(route('tax.returns.post', $return))->assertSessionHasNoErrors();
        $return->refresh();
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
        $this->post(route('banking.auto', $account))->assertSessionHasErrors('reconciliation');
        $bank = $account->transactions()->firstOrFail();
        $payment = $return->payments()->firstOrFail();
        $this->post(route('banking.reconcile', $bank), ['payment' => 'tax:'.$payment->id, 'allocated_amount' => '10.50', 'account_id' => $account->id, 'revision' => app(ControlledReconciliation::class)->revision($bank->fresh()), 'operation_key' => (string) Str::uuid(), 'reason' => 'Synthetic reviewed tax payment', 'confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, BankReconciliation::whereNotNull('tax_return_payment_id')->count());
    }

    public function test_negative_vat_defaults_to_compensation_and_cannot_be_recorded_as_an_ordinary_refund(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ExpenseCategory::create(['code' => 'VAT-IN', 'name' => 'Input VAT', 'is_active' => true]);
        EconomicFixture::expense([
            'expense_category_id' => $category->id,
            'created_by' => $admin->id,
            'number' => 'EXP-VAT-NEG-1',
            'type' => 'invoice',
            'expense_date' => '2026-02-10',
            'subtotal' => 100,
            'tax_amount' => 21,
            'amount' => 121,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        app(AccountingSynchronizer::class)->sync($admin->id);

        $this->actingAs($admin)->post(route('tax.returns.prepare'), ['year' => 2026, 'quarter' => 1])
            ->assertSessionHasNoErrors();
        $return = TaxReturn::firstOrFail();
        $this->post(route('tax.returns.review', $return), ['reason' => 'Reviewed registers'])->assertSessionHasNoErrors();
        $this->post(route('tax.returns.post', $return))->assertSessionHasNoErrors();
        $return->refresh();
        $this->assertSame('compensate', $return->result_option);
        $this->assertSame('awaiting_election', $return->settlement_status);

        $filing = [
            'filing_reference' => 'AEAT-Q1-NEG',
            'filed_on' => '2026-04-20',
            'receipt' => UploadedFile::fake()->create('modelo-303.pdf', 10, 'application/pdf'),
        ];
        $this->post(route('tax.returns.file', $return), [...$filing, 'negative_result_option' => 'refund'])
            ->assertSessionHasErrors('negative_result_option');
        $this->post(route('tax.returns.file', $return), [...$filing, 'negative_result_option' => 'compensate'])
            ->assertSessionHasNoErrors();

        $return->refresh();
        $this->assertSame('filed', $return->status);
        $this->assertSame('compensate', $return->result_option);
        $this->assertSame('carried_forward', $return->settlement_status);
        $this->assertSame(64, strlen((string) $return->receipt_sha256));
        Storage::disk('local')->assertExists($return->receipt_path);
        $this->post(route('tax.returns.payments.store', $return), ['paid_on' => '2026-04-21', 'amount' => 21, 'method' => 'bank_transfer'])
            ->assertStatus(422);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => TaxReturn::class, 'auditable_id' => $return->id, 'event' => 'updated']);
    }
}
