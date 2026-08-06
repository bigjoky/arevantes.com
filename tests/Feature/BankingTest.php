<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankFinancialProduct;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\BankTransactionPosting;
use App\Models\BankTransfer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LedgerAccount;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_cards_and_facilities_are_managed_in_the_treasury_position(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $technician = User::factory()->create(['role' => 'technician']);
        $this->actingAs($user)->post(route('banking.accounts.store'), [
            'name' => 'Main settlement account',
            'currency' => 'EUR',
            'opening_balance' => 0,
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $account = BankAccount::where('name', 'Main settlement account')->firstOrFail();
        $liability = LedgerAccount::where('code', '5201')->firstOrFail();
        $base = [
            'bank_account_id' => $account->id,
            'ledger_account_id' => $liability->id,
            'provider' => 'Arevantes Bank',
            'currency' => 'eur',
            'annual_fee' => 0,
            'is_active' => true,
        ];

        $this->post(route('banking.products.store'), [...$base,
            'type' => 'credit_card',
            'name' => 'Purchasing card',
            'identifier_last_four' => '4242',
            'credit_limit' => 10000,
            'used_amount' => 2500,
            'billing_day' => 25,
            'payment_day' => 5,
            'expires_on' => now()->addDays(30)->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->post(route('banking.products.store'), [...$base,
            'type' => 'credit_facility',
            'name' => 'Working capital facility',
            'contract_reference' => 'POL-2026-01',
            'credit_limit' => 50000,
            'used_amount' => 60000,
            'annual_interest_rate' => 4.25,
        ])->assertSessionHasErrors('used_amount');
        $this->post(route('banking.products.store'), [...$base,
            'type' => 'credit_facility',
            'name' => 'Working capital facility',
            'contract_reference' => 'POL-2026-01',
            'credit_limit' => 50000,
            'used_amount' => 20000,
            'annual_interest_rate' => 4.25,
        ])->assertSessionHasNoErrors();

        $card = BankFinancialProduct::where('type', 'credit_card')->firstOrFail();
        $this->assertSame('EUR', $card->currency);
        $this->assertSame('4242', $card->identifier_last_four);
        $this->assertNull($card->contract_reference);
        $this->patch(route('banking.products.update', $card), [...$base,
            'type' => 'credit_card',
            'name' => 'Purchasing card',
            'identifier_last_four' => '4242',
            'credit_limit' => 10000,
            'used_amount' => 3000,
            'expires_on' => now()->addDays(30)->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame('3000.00', $card->fresh()->used_amount);

        $this->get(route('banking.index', ['account' => $account->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('products', 2)
                ->where('products.0.available_amount', 7000)
                ->where('products.0.utilization', 30)
                ->where('products.0.expires_soon', true)
                ->where('productSummary.0.currency', 'EUR')
                ->where('productSummary.0.limit', 60000)
                ->where('productSummary.0.used', 23000)
                ->where('productSummary.0.available', 37000));

        $this->actingAs($technician)->post(route('banking.products.store'), [...$base,
            'type' => 'credit_card',
            'name' => 'Forbidden card',
            'credit_limit' => 1000,
            'used_amount' => 0,
        ])->assertForbidden();
    }

    public function test_multiple_cards_can_draw_from_one_shared_credit_facility(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user)->post(route('banking.accounts.store'), [
            'name' => 'Shared cards settlement',
            'currency' => 'EUR',
            'opening_balance' => 0,
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $account = BankAccount::where('name', 'Shared cards settlement')->firstOrFail();
        $liability = LedgerAccount::where('code', '5201')->firstOrFail();
        $common = [
            'bank_account_id' => $account->id,
            'ledger_account_id' => $liability->id,
            'provider' => 'Shared Bank',
            'currency' => 'EUR',
            'annual_fee' => 0,
            'is_active' => true,
        ];
        $this->post(route('banking.products.store'), [...$common,
            'type' => 'credit_facility',
            'name' => 'Corporate card pool',
            'contract_reference' => 'POOL-10K',
            'credit_limit' => 10000,
            'used_amount' => 0,
        ])->assertSessionHasNoErrors();
        $facility = BankFinancialProduct::where('type', 'credit_facility')->firstOrFail();

        foreach ([['Operations card', '1111', 3000], ['Purchasing card', '2222', 2000]] as [$name, $lastFour, $used]) {
            $this->post(route('banking.products.store'), [...$common,
                'type' => 'credit_card',
                'name' => $name,
                'identifier_last_four' => $lastFour,
                'shared_credit_facility_id' => $facility->id,
                'used_amount' => $used,
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame('5000.00', $facility->fresh()->used_amount);
        $this->assertSame(2, $facility->linkedCards()->count());
        $this->assertTrue($facility->linkedCards->every(fn ($card) => $card->credit_limit === '10000.00'));

        $purchasing = BankFinancialProduct::where('name', 'Purchasing card')->firstOrFail();
        $this->patch(route('banking.products.update', $purchasing), [...$common,
            'type' => 'credit_card',
            'name' => $purchasing->name,
            'identifier_last_four' => '2222',
            'shared_credit_facility_id' => $facility->id,
            'used_amount' => 8000,
        ])->assertSessionHasErrors('used_amount');
        $this->assertSame('2000.00', $purchasing->fresh()->used_amount);
        $this->assertSame('5000.00', $facility->fresh()->used_amount);

        $this->get(route('banking.index', ['account' => $account->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('productSummary.0.limit', 10000)
                ->where('productSummary.0.used', 5000)
                ->where('productSummary.0.available', 5000)
                ->where('products.0.shared_credit_facility.name', 'Corporate card pool')
                ->where('products.0.available_amount', 5000)
                ->where('products.1.available_amount', 5000)
                ->has('products.2.linked_cards', 2));
    }

    public function test_existing_independent_cards_can_be_grouped_into_a_new_shared_limit(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user)->post(route('banking.accounts.store'), [
            'name' => 'Existing cards settlement',
            'currency' => 'EUR',
            'opening_balance' => 0,
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $account = BankAccount::where('name', 'Existing cards settlement')->firstOrFail();
        $liability = LedgerAccount::where('code', '5201')->firstOrFail();
        $common = [
            'type' => 'credit_card',
            'bank_account_id' => $account->id,
            'ledger_account_id' => $liability->id,
            'provider' => 'BBVA',
            'currency' => 'EUR',
            'credit_limit' => 4800,
            'annual_fee' => 0,
            'is_active' => true,
        ];
        foreach ([['Card one', '1111', 311.10], ['Card two', '2222', 0]] as [$name, $lastFour, $used]) {
            $this->post(route('banking.products.store'), [...$common,
                'name' => $name,
                'identifier_last_four' => $lastFour,
                'used_amount' => $used,
            ])->assertSessionHasNoErrors();
        }
        $cards = BankFinancialProduct::where('type', 'credit_card')->get();

        $this->post(route('banking.shared-credit-facilities.store'), [
            'name' => 'BBVA shared card limit',
            'contract_reference' => 'BBVA-POOL',
            'credit_limit' => 4800,
            'card_ids' => $cards->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

        $facility = BankFinancialProduct::where('type', 'credit_facility')->firstOrFail();
        $this->assertSame('311.10', $facility->used_amount);
        $this->assertSame(2, $facility->linkedCards()->count());
        $this->assertSame([$facility->id], $cards->map(fn ($card) => $card->fresh()->shared_credit_facility_id)->unique()->values()->all());
        $this->get(route('banking.index', ['account' => $account->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('productSummary.0.limit', 4800)
                ->where('productSummary.0.used', 311.1)
                ->where('productSummary.0.available', 4488.9));
    }

    public function test_european_csv_is_deduplicated_and_reconciled_with_recorded_payments(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Main account', 'currency' => 'EUR', 'opening_balance' => 1000, 'is_active' => true]);
        $sale = SalesDocument::create(['type' => 'invoice', 'number' => 'FAC-2026-00001', 'status' => 'paid', 'issue_date' => '2026-07-01', 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 121, 'customer_snapshot' => ['first_name' => 'Ana']]);
        $sale->payments()->create(['created_by' => $user->id, 'paid_at' => '2026-07-13', 'amount' => 121, 'method' => 'bank_transfer', 'reference' => 'BANK-123']);
        $supplier = Supplier::create(['legal_name' => 'Supplier SL', 'country' => 'ES', 'payment_terms_days' => 30]);
        $invoice = PurchaseDocument::create(['type' => 'supplier_invoice', 'supplier_id' => $supplier->id, 'number' => 'FR-2026-00001', 'status' => 'paid', 'issue_date' => '2026-07-01', 'subtotal' => 50, 'tax_amount' => 10, 'amount' => 60, 'paid_amount' => 60]);
        SupplierPayment::create(['purchase_document_id' => $invoice->id, 'created_by' => $user->id, 'paid_at' => '2026-07-14', 'amount' => 60, 'method' => 'bank_transfer', 'reference' => 'PAY-1']);
        $csv = "Fecha;Importe;Concepto;Referencia\n13/07/2026;121,00;Ingreso factura;BANK-123\n14/07/2026;-60,00;Pago proveedor;PAY-1\n";
        $this->actingAs($user);

        $this->post(route('banking.import', $account), ['statement' => UploadedFile::fake()->createWithContent('statement.csv', $csv)])->assertRedirect();
        $this->assertSame(2, BankTransaction::count());
        $this->assertDatabaseHas('bank_transactions', ['amount' => '121', 'description' => 'Ingreso factura']);
        $this->assertDatabaseHas('bank_transactions', ['amount' => '-60', 'description' => 'Pago proveedor']);
        $this->post(route('banking.import', $account), ['statement' => UploadedFile::fake()->createWithContent('statement.csv', $csv)])->assertRedirect();
        $this->assertSame(2, BankTransaction::count());

        $this->post(route('banking.auto', $account))->assertRedirect();
        $this->assertSame(2, BankReconciliation::count());
        $this->assertSame(0, BankTransaction::where('status', 'pending')->count());
        $reconciliation = BankReconciliation::firstOrFail();
        $this->delete(route('banking.reconciliations.undo', $reconciliation))->assertRedirect();
        $this->assertSame('pending', $reconciliation->transaction->refresh()->status);
    }

    public function test_norma_43_imports_debits_credits_and_complementary_concepts(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'AEB account', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $header = '11'.'0049'.'1234'.'1234567890'.'260701'.'260731'.'2'.str_pad('0', 14, '0', STR_PAD_LEFT).'978'.'2'.str_repeat(' ', 26);
        $credit = '22'.'0049'.'1234'.'260713'.'260713'.'01'.'001'.'2'.str_pad('12100', 14, '0', STR_PAD_LEFT).str_pad('DOC1', 10).str_pad('FAC2026', 12).str_pad('TRANSFERENCIA', 16);
        $concept = '23'.'01'.str_pad('COBRO FACTURA FAC-2026-00001', 38).str_repeat(' ', 40);
        $debit = '22'.'0049'.'1234'.'260714'.'260714'.'02'.'002'.'1'.str_pad('6050', 14, '0', STR_PAD_LEFT).str_pad('DOC2', 10).str_pad('RECIBO', 12).str_pad('PROVEEDOR', 16);
        $footer = '33'.'0049'.'1234'.'1234567890'.'00001'.str_pad('6050', 14, '0', STR_PAD_LEFT).'00001'.str_pad('12100', 14, '0', STR_PAD_LEFT).'2'.str_pad('6050', 14, '0', STR_PAD_LEFT).'978'.str_repeat(' ', 4);
        $file = implode("\r\n", [$header, $credit, $concept, $debit, $footer])."\r\n";

        $this->actingAs($user)->post(route('banking.import', $account), [
            'statement' => UploadedFile::fake()->createWithContent('extracto.n43', $file),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, BankTransaction::where('amount', 121)->whereDate('booked_on', '2026-07-13')->count());
        $this->assertSame(1, BankTransaction::where('amount', -60.5)->whereDate('value_on', '2026-07-14')->count());
        $transaction = BankTransaction::where('amount', 121)->firstOrFail();
        $this->assertStringContainsString('COBRO FACTURA', $transaction->description);
        $this->assertSame('norma43', $transaction->raw_data['format']);
        $this->assertDatabaseHas('bank_statement_imports', ['format' => 'norma43', 'created_transactions' => 2, 'control_valid' => true]);
        $this->get(route('banking.index', ['account' => $account->id]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('control.matchesStatement', true)->where('control.statementDifference', 0));

        $this->actingAs($user)->post(route('banking.import', $account), [
            'statement' => UploadedFile::fake()->createWithContent('extracto.txt', $file),
        ])->assertRedirect();
        $this->assertSame(2, BankTransaction::count());
    }

    public function test_unmatched_bank_movement_can_be_posted_reversed_and_reclassified(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Operating account', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $transaction = $account->transactions()->create(['booked_on' => now(), 'amount' => -12.5, 'currency' => 'EUR', 'description' => 'Bank fee', 'import_hash' => hash('sha256', 'bank-fee'), 'status' => 'pending']);

        $this->actingAs($user)->post(route('banking.transactions.post', $transaction), ['counterpart_account' => '629'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('accounted', $transaction->fresh()->status);
        $posting = BankTransactionPosting::with('journalEntry.lines.account')->firstOrFail();
        $this->assertSame('12.50', $posting->journalEntry->lines->firstWhere('account.code', '629')->debit);
        $this->assertSame('12.50', $posting->journalEntry->lines->firstWhere('account.code', '572')->credit);

        $this->post(route('banking.postings.reverse', $posting))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertNotNull($posting->fresh()->reversal_entry_id);
        $this->post(route('banking.transactions.post', $transaction), ['counterpart_account' => '623'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, BankTransactionPosting::count());
        $this->assertSame('accounted', $transaction->fresh()->status);
    }

    public function test_own_account_transfers_use_distinct_bank_subaccounts_and_can_be_reversed(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user);
        $this->post(route('banking.accounts.store'), ['name' => 'Bank A', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true])->assertRedirect();
        $this->post(route('banking.accounts.store'), ['name' => 'Bank B', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true])->assertRedirect();
        $from = BankAccount::where('name', 'Bank A')->with('ledgerAccount')->firstOrFail();
        $to = BankAccount::where('name', 'Bank B')->with('ledgerAccount')->firstOrFail();
        $this->assertNotSame($from->ledgerAccount->code, $to->ledgerAccount->code);
        $outgoing = $from->transactions()->create(['booked_on' => now(), 'amount' => -500, 'currency' => 'EUR', 'description' => 'Own transfer', 'reference' => 'TR-55', 'import_hash' => hash('sha256', 'transfer-out'), 'status' => 'pending']);
        $incoming = $to->transactions()->create(['booked_on' => now()->addDay(), 'amount' => 500, 'currency' => 'EUR', 'description' => 'Own transfer', 'reference' => 'TR-55', 'import_hash' => hash('sha256', 'transfer-in'), 'status' => 'pending']);

        $this->post(route('banking.auto', $from))->assertRedirect()->assertSessionHasNoErrors();
        $transfer = BankTransfer::with('journalEntry.lines.account')->firstOrFail();
        $this->assertSame('reconciled', $outgoing->fresh()->status);
        $this->assertSame('reconciled', $incoming->fresh()->status);
        $this->assertSame('500.00', $transfer->journalEntry->lines->firstWhere('account.code', $to->ledgerAccount->code)->debit);
        $this->assertSame('500.00', $transfer->journalEntry->lines->firstWhere('account.code', $from->ledgerAccount->code)->credit);
        $this->post(route('banking.transfers.reverse', $transfer))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', $outgoing->fresh()->status);
        $this->assertSame('pending', $incoming->fresh()->status);
    }

    public function test_payment_reconciliation_reclassifies_generic_bank_account_to_specific_subaccount(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user);
        $this->post(route('banking.accounts.store'), ['name' => 'Collections bank', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true])->assertRedirect();
        $account = BankAccount::where('name', 'Collections bank')->with('ledgerAccount')->firstOrFail();
        $sale = SalesDocument::create(['type' => 'invoice', 'number' => 'FAC-BANK-1', 'status' => 'paid', 'issue_date' => now(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 0, 'amount' => 100, 'paid_amount' => 100, 'customer_snapshot' => []]);
        $payment = $sale->payments()->create(['created_by' => $user->id, 'paid_at' => now(), 'amount' => 100, 'method' => 'bank_transfer', 'reference' => 'COLL-1']);
        $transaction = $account->transactions()->create(['booked_on' => now(), 'amount' => 100, 'currency' => 'EUR', 'description' => 'Customer receipt', 'reference' => 'COLL-1', 'import_hash' => hash('sha256', 'collections'), 'status' => 'pending']);

        $this->post(route('banking.reconcile', $transaction), ['payment' => 'customer:'.$payment->id])->assertRedirect()->assertSessionHasNoErrors();
        $reconciliation = BankReconciliation::with('journalEntry.lines.account')->firstOrFail();
        $this->assertSame('100.00', $reconciliation->journalEntry->lines->firstWhere('account.code', $account->ledgerAccount->code)->debit);
        $this->assertSame('100.00', $reconciliation->journalEntry->lines->firstWhere('account.code', '572')->credit);
        $this->delete(route('banking.reconciliations.undo', $reconciliation))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', $transaction->fresh()->status);
    }

    public function test_incoming_bank_movement_can_create_and_reconcile_customer_receipt(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Collections', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $invoice = SalesDocument::create([
            'type' => 'invoice', 'number' => 'FAC-2026-0042', 'status' => 'issued',
            'issue_date' => now()->subDays(10), 'due_date' => now()->addDays(20),
            'issued_at' => now(), 'locked_at' => now(), 'subtotal' => 100,
            'discount_amount' => 0, 'tax_amount' => 21, 'amount' => 121,
            'paid_amount' => 0, 'customer_snapshot' => [],
        ]);
        $transaction = $account->transactions()->create([
            'booked_on' => now(), 'amount' => 40, 'currency' => 'EUR',
            'description' => 'Cobro FAC20260042', 'reference' => 'FAC-2026-0042',
            'import_hash' => hash('sha256', 'direct-customer-receipt'), 'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('banking.index', ['account' => $account->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transactions.data.0.candidates.0.value', 'customer_invoice:'.$invoice->id)
                ->where('transactions.data.0.candidates.0.creates_payment', true));
        $this->post(route('banking.reconcile', $transaction), ['payment' => 'customer_invoice:'.$invoice->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $payment = SalesDocumentPayment::firstOrFail();
        $this->assertSame('40.00', $payment->amount);
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame('40.00', $invoice->fresh()->paid_amount);
        $this->assertSame('reconciled', $transaction->fresh()->status);
        $this->assertDatabaseHas('bank_reconciliations', ['sales_document_payment_id' => $payment->id, 'method' => 'manual']);
        $this->assertNotNull($payment->accountingEntry);

        $this->delete(route('banking.reconciliations.undo', $payment->reconciliation))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sales_document_payments', ['id' => $payment->id]);
        $this->assertSame('issued', $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->paid_amount);
        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertDatabaseHas('journal_entries', ['event' => 'reversal', 'reverses_entry_id' => $payment->accountingEntry->id]);
    }

    public function test_auto_reconciliation_creates_supplier_invoice_and_expense_payments_from_exact_references(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $supplier = Supplier::create(['legal_name' => 'Solar Supply SL', 'country' => 'ES', 'payment_terms_days' => 30]);
        $account = BankAccount::create(['name' => 'Payments', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $invoice = PurchaseDocument::create([
            'type' => 'supplier_invoice', 'supplier_id' => $supplier->id,
            'number' => 'FR-2026-0008', 'supplier_number' => 'SS-8841',
            'status' => 'registered', 'issue_date' => now()->subDays(5),
            'due_date' => now()->addDays(25), 'subtotal' => 50, 'tax_amount' => 10.5,
            'amount' => 60.5, 'paid_amount' => 0,
        ]);
        $expense = Expense::create([
            'expense_category_id' => ExpenseCategory::firstOrFail()->id,
            'supplier_id' => $supplier->id, 'created_by' => $user->id,
            'number' => 'EXP-2026-0009', 'document_number' => 'TICKET-778',
            'type' => 'receipt', 'merchant_name' => 'Solar Supply',
            'expense_date' => now()->subDay(), 'due_date' => now(),
            'subtotal' => 20, 'tax_amount' => 4.2, 'amount' => 24.2,
            'paid_amount' => 0, 'status' => 'pending',
        ]);
        $account->transactions()->create([
            'booked_on' => now(), 'amount' => -60.5, 'currency' => 'EUR',
            'description' => 'Pago factura SS8841', 'reference' => 'SS-8841',
            'import_hash' => hash('sha256', 'direct-supplier-payment'), 'status' => 'pending',
        ]);
        $account->transactions()->create([
            'booked_on' => now(), 'amount' => -24.2, 'currency' => 'EUR',
            'description' => 'Pago TICKET778', 'reference' => 'TICKET-778',
            'import_hash' => hash('sha256', 'direct-expense-payment'), 'status' => 'pending',
        ]);

        $this->actingAs($user)->post(route('banking.auto', $account))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, SupplierPayment::count());
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('60.50', $invoice->fresh()->paid_amount);
        $this->assertSame('paid', $expense->fresh()->status);
        $this->assertSame('24.20', $expense->fresh()->paid_amount);
        $this->assertSame(2, BankReconciliation::where('method', 'automatic')->count());
        $this->assertSame(0, BankTransaction::where('status', 'pending')->count());
    }

    public function test_auto_reconciliation_does_not_create_payment_from_amount_alone(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Safe matching', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        SalesDocument::create([
            'type' => 'invoice', 'number' => 'FAC-2026-0099', 'status' => 'issued',
            'issue_date' => now(), 'due_date' => now()->addMonth(), 'issued_at' => now(),
            'locked_at' => now(), 'subtotal' => 100, 'discount_amount' => 0,
            'tax_amount' => 21, 'amount' => 121, 'paid_amount' => 0,
            'customer_snapshot' => [],
        ]);
        $transaction = $account->transactions()->create([
            'booked_on' => now(), 'amount' => 121, 'currency' => 'EUR',
            'description' => 'Transferencia recibida sin concepto', 'reference' => null,
            'import_hash' => hash('sha256', 'ambiguous-amount-only'), 'status' => 'pending',
        ]);

        $this->actingAs($user)->post(route('banking.auto', $account))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertSame(0, SalesDocumentPayment::count());
        $this->assertSame(0, BankReconciliation::count());
    }
}
