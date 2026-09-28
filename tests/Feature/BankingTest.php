<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankFinancialProduct;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationRule;
use App\Models\BankReconciliationRuleVersion;
use App\Models\BankReconciliationRun;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\BankTransactionPosting;
use App\Models\BankTransfer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
        $this->travelTo('2026-08-06');
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
            'bank_account_id' => $account->id,
            'billing_day' => 31,
            'payment_day' => 5,
            'card_ids' => $cards->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

        $facility = BankFinancialProduct::where('type', 'credit_facility')->firstOrFail();
        $this->assertSame('311.10', $facility->used_amount);
        $this->assertSame(31, $facility->billing_day);
        $this->assertSame(5, $facility->payment_day);
        $this->assertSame($account->id, $facility->bank_account_id);
        $this->assertSame(2, $facility->linkedCards()->count());
        $this->assertSame([$facility->id], $cards->map(fn ($card) => $card->fresh()->shared_credit_facility_id)->unique()->values()->all());
        $this->get(route('banking.index', ['account' => $account->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('productSummary.0.limit', 4800)
                ->where('productSummary.0.used', 311.1)
                ->where('productSummary.0.available', 4488.9)
                ->where('products.0.next_closing_on', '2026-08-31')
                ->where('products.0.next_payment_on', '2026-09-05')
                ->where('products.0.payment_bank_account.name', 'Existing cards settlement')
                ->where('products.2.next_payment_on', '2026-09-05'));
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

    public function test_norma_43_windows_1252_references_preview_and_import_without_json_errors(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        $account = BankAccount::create(['name' => 'BBVA test', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $header = '11'.'0182'.'1234'.'1234567890'.'260701'.'260731'.'2'.str_pad('0', 14, '0', STR_PAD_LEFT).'978'.'2'.str_repeat(' ', 26);
        $reference = mb_convert_encoding('PEÑITA', 'Windows-1252', 'UTF-8');
        $movement = '22'.'0182'.'1234'.'260713'.'260713'.'01'.'001'.'2'.str_pad('12100', 14, '0', STR_PAD_LEFT).str_pad('DOC1', 10).str_pad($reference, 12).str_pad('TRANSFERENCIA', 16);
        $concept = '2301'.str_pad(mb_convert_encoding('REPARACIÓN…', 'Windows-1252', 'UTF-8'), 76);
        $footer = '33'.'0182'.'1234'.'1234567890'.'00000'.str_pad('0', 14, '0', STR_PAD_LEFT).'00001'.str_pad('12100', 14, '0', STR_PAD_LEFT).'2'.str_pad('12100', 14, '0', STR_PAD_LEFT).'978'.str_repeat(' ', 4);
        $contents = implode("\r\n", [$header, $movement, $concept, $footer]);
        $this->postJson(route('banking.import.preview', $account), ['statement' => UploadedFile::fake()->createWithContent('bbva.n43', $contents)])
            ->assertOk()->assertJsonPath('created', 1);
        $this->assertSame(0, BankTransaction::count());
        $this->post(route('banking.import', $account), ['statement' => UploadedFile::fake()->createWithContent('bbva.n43', $contents)])->assertRedirect()->assertSessionHasNoErrors();
        $transaction = BankTransaction::sole();
        $this->assertStringContainsString('PEÑITA', $transaction->reference);
        $this->assertStringContainsString('REPARACIÓN…', $transaction->description);
        $this->assertStringContainsString('PEÑITA', $transaction->raw_data['record_22']);
    }

    public function test_norma_43_preserves_legitimate_identical_movements_and_deduplicates_reimports(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Annual AEB account', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $header = '11'.'0049'.'1234'.'1234567890'.'260101'.'261231'.'2'.str_pad('0', 14, '0', STR_PAD_LEFT).'978'.'2'.str_repeat(' ', 26);
        $debit = '22'.'0049'.'1234'.'260715'.'260715'.'02'.'002'.'1'.str_pad('6050', 14, '0', STR_PAD_LEFT).str_pad('SAME-DOC', 10).str_pad('SAME-REF', 12).str_pad('SAME CONCEPT', 16);
        $footer = '33'.'0049'.'1234'.'1234567890'.'00002'.str_pad('12100', 14, '0', STR_PAD_LEFT).'00000'.str_pad('0', 14, '0', STR_PAD_LEFT).'1'.str_pad('12100', 14, '0', STR_PAD_LEFT).'978'.str_repeat(' ', 4);
        $file = implode("\r\n", [$header, $debit, $debit, $footer])."\r\n";
        $this->actingAs($user);

        $this->postJson(route('banking.import.preview', $account), [
            'statement' => UploadedFile::fake()->createWithContent('annual-2026.n43', $file),
        ])->assertOk()->assertJson([
            'format' => 'norma43',
            'total' => 2,
            'created' => 2,
            'duplicates' => 0,
            'period_from' => '2026-01-01',
            'period_to' => '2026-12-31',
            'opening_balance' => 0,
            'closing_balance' => -121,
            'control_valid' => true,
            'source_account' => ['entity' => '0049', 'office' => '1234', 'account' => '1234567890'],
            'sample' => [
                ['amount' => -60.5, 'status' => 'new'],
                ['amount' => -60.5, 'status' => 'new'],
            ],
        ]);
        $this->assertSame(0, $account->transactions()->count());

        $this->post(route('banking.import', $account), [
            'statement' => UploadedFile::fake()->createWithContent('annual-2026.n43', $file),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, $account->transactions()->count());
        $this->assertSame(2, $account->transactions()->where('amount', -60.5)->count());
        $this->assertDatabaseHas('bank_statement_imports', ['filename' => 'annual-2026.n43', 'created_transactions' => 2, 'duplicate_transactions' => 0, 'control_valid' => true]);

        $this->post(route('banking.import', $account), [
            'statement' => UploadedFile::fake()->createWithContent('annual-2026-reimport.n43', $file),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, $account->transactions()->count());
        $this->assertDatabaseHas('bank_statement_imports', ['filename' => 'annual-2026-reimport.n43', 'created_transactions' => 0, 'duplicate_transactions' => 2, 'control_valid' => true]);
    }

    public function test_norma_43_rejects_multiple_account_blocks_and_an_iban_mismatch_atomically(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Validated AEB account', 'iban' => 'ES0000491234001234567890', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $block = function (string $entity, string $office, string $number, string $reference): array {
            $header = '11'.$entity.$office.$number.'260101'.'260131'.'2'.str_pad('0', 14, '0', STR_PAD_LEFT).'978'.'2'.str_repeat(' ', 26);
            $credit = '22'.$entity.$office.'260115'.'260115'.'01'.'001'.'2'.str_pad('10000', 14, '0', STR_PAD_LEFT).str_pad($reference, 10).str_pad('TRANSFER', 12).str_pad('CUSTOMER', 16);
            $footer = '33'.$entity.$office.$number.'00000'.str_pad('0', 14, '0', STR_PAD_LEFT).'00001'.str_pad('10000', 14, '0', STR_PAD_LEFT).'2'.str_pad('10000', 14, '0', STR_PAD_LEFT).'978'.str_repeat(' ', 4);

            return [$header, $credit, $footer];
        };
        $matching = implode("\r\n", $block('0049', '1234', '1234567890', 'ACCOUNT-1'))."\r\n";

        $this->actingAs($user)->post(route('banking.import', $account), [
            'statement' => UploadedFile::fake()->createWithContent('matching.n43', $matching),
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $account->transactions()->count());
        $this->assertSame('0049', BankStatementImport::firstOrFail()->control_data['account']['entity']);

        $mismatchedAccount = BankAccount::create(['name' => 'Wrong selected account', 'iban' => 'ES0000491234009999999999', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $this->post(route('banking.import', $mismatchedAccount), [
            'statement' => UploadedFile::fake()->createWithContent('mismatch.n43', $matching),
        ])->assertSessionHasErrors('statement');
        $this->assertSame(0, $mismatchedAccount->transactions()->count());

        $multiple = implode("\r\n", [
            ...$block('0049', '1234', '1234567890', 'ACCOUNT-1'),
            ...$block('2100', '4321', '0987654321', 'ACCOUNT-2'),
        ])."\r\n";
        $this->post(route('banking.import', $account), [
            'statement' => UploadedFile::fake()->createWithContent('multi-account.n43', $multiple),
        ])->assertSessionHasErrors('statement');
        $this->assertSame(1, $account->transactions()->count());
        $this->assertSame(1, BankStatementImport::count());
    }

    public function test_norma_43_origin_office_can_differ_without_bypassing_account_controls(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'commercial']));
        $account = BankAccount::create(['name' => 'Origin office test', 'iban' => 'ES0001821234001234567890', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $header = '11'.'0182'.'1234'.'1234567890'.'260101'.'260131'.'2'.str_pad('0', 14, '0', STR_PAD_LEFT).'978'.'2'.str_repeat(' ', 29);
        $movement = '22'.'    '.'9876'.'260115'.'260115'.'01'.'001'.'2'.str_pad('10000', 14, '0', STR_PAD_LEFT).str_pad('DOC1', 10).str_pad('REF', 12).str_pad('TRANSFER', 16);
        $footer = '33'.'0182'.'1234'.'1234567890'.'00000'.str_pad('0', 14, '0', STR_PAD_LEFT).'00001'.str_pad('10000', 14, '0', STR_PAD_LEFT).'2'.str_pad('10000', 14, '0', STR_PAD_LEFT).'978'.str_repeat(' ', 4);
        $file = fn (string $control) => UploadedFile::fake()->createWithContent('origin-office.n43', implode("\r\n", [$header, $movement, $control]));

        $this->postJson(route('banking.import.preview', $account), ['statement' => $file($footer)])
            ->assertOk()->assertJsonPath('created', 1)->assertJsonPath('control_valid', true);
        $this->assertSame(0, BankTransaction::count());
        $this->post(route('banking.import', $account), ['statement' => $file($footer)])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $account->transactions()->count());

        // Account identity still comes from the header and must match the footer.
        $legacyReference = trim(substr($movement, 42, 10).' '.substr($movement, 52, 12).' '.substr($movement, 64, 16));
        $legacyHash = hash('sha256', implode('|', ['2026-01-15', '2026-01-15', '100.00', $legacyReference, 'Concept 01/001']));
        $this->assertSame($legacyHash, $account->transactions()->sole()->import_hash);
        $this->postJson(route('banking.import.preview', $account), ['statement' => $file($footer)])
            ->assertOk()->assertJsonPath('created', 0)->assertJsonPath('duplicates', 1);

        $wrongFooter = substr_replace($footer, '9999', 6, 4);
        $this->postJson(route('banking.import.preview', $account), ['statement' => $file($wrongFooter)])
            ->assertUnprocessable()->assertJsonValidationErrors('statement');
        $this->assertSame(1, $account->transactions()->count());
    }

    public function test_statement_import_can_be_rolled_back_with_audit_and_reimported(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Import control account', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $csv = "Fecha;Importe;Concepto;Referencia\n01/08/2026;-25,00;Bank service;FEE-01\n02/08/2026;100,00;Customer receipt;RCPT-01\n";
        $this->actingAs($user)->post(route('banking.import', $account), [
            'statement' => UploadedFile::fake()->createWithContent('wrong-account.csv', $csv),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $statementImport = BankStatementImport::firstOrFail();
        $this->assertSame(2, $statementImport->transactions()->count());
        $this->assertSame($statementImport->id, BankTransaction::firstOrFail()->bank_statement_import_id);
        $this->get(route('banking.index', ['account' => $account->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('imports.0.transactions_count', 2)
                ->where('imports.0.pending_transactions_count', 2)
                ->where('imports.0.can_rollback', true));

        $this->delete(route('banking.imports.rollback', $statementImport), [
            'reason' => 'Imported into the wrong bank account.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, BankTransaction::count());
        $this->assertDatabaseHas('bank_statement_imports', [
            'id' => $statementImport->id,
            'rolled_back_by' => $user->id,
            'rollback_reason' => 'Imported into the wrong bank account.',
        ]);

        $this->post(route('banking.import', $account), [
            'statement' => UploadedFile::fake()->createWithContent('correct-account.csv', $csv),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, BankTransaction::count());
        $this->assertDatabaseHas('bank_statement_imports', ['filename' => 'correct-account.csv', 'created_transactions' => 2, 'duplicate_transactions' => 0]);
    }

    public function test_statement_can_be_previewed_without_writes_and_revalidates_duplicates(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Preview account', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $csv = "Fecha;Fecha valor;Importe;Concepto;Referencia\n01/08/2026;02/08/2026;-25,00;Bank service;FEE-PREVIEW\n03/08/2026;03/08/2026;100,00;Customer receipt;RCPT-PREVIEW\n";
        $this->actingAs($user);

        $this->postJson(route('banking.import.preview', $account), [
            'statement' => UploadedFile::fake()->createWithContent('preview.csv', $csv),
        ])->assertOk()->assertJson([
            'filename' => 'preview.csv',
            'format' => 'csv',
            'total' => 2,
            'created' => 2,
            'duplicates' => 0,
            'control_valid' => null,
            'sample' => [
                ['booked_on' => '2026-08-01', 'value_on' => '2026-08-02', 'amount' => -25, 'reference' => 'FEE-PREVIEW', 'status' => 'new'],
                ['booked_on' => '2026-08-03', 'value_on' => '2026-08-03', 'amount' => 100, 'reference' => 'RCPT-PREVIEW', 'status' => 'new'],
            ],
        ]);
        $this->assertSame(0, BankTransaction::count());
        $this->assertSame(0, BankStatementImport::count());

        $this->post(route('banking.import', $account), [
            'statement' => UploadedFile::fake()->createWithContent('preview.csv', $csv),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->postJson(route('banking.import.preview', $account), [
            'statement' => UploadedFile::fake()->createWithContent('preview.csv', $csv),
        ])->assertOk()->assertJson([
            'total' => 2,
            'created' => 0,
            'duplicates' => 2,
            'sample' => [
                ['status' => 'duplicate'],
                ['status' => 'duplicate'],
            ],
        ]);
        $this->assertSame(2, BankTransaction::count());
        $this->assertSame(1, BankStatementImport::count());
    }

    public function test_statement_import_rollback_is_blocked_after_a_movement_is_processed(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Protected import account', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $csv = "Fecha;Importe;Concepto;Referencia\n01/08/2026;-25,00;Bank service;FEE-02\n";
        $this->actingAs($user)->post(route('banking.import', $account), [
            'statement' => UploadedFile::fake()->createWithContent('protected.csv', $csv),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $statementImport = BankStatementImport::firstOrFail();
        $statementImport->transactions()->firstOrFail()->update(['status' => 'accounted']);

        $this->delete(route('banking.imports.rollback', $statementImport), [
            'reason' => 'Attempting to remove a processed import.',
        ])->assertSessionHasErrors('reason');

        $this->assertSame(1, BankTransaction::count());
        $this->assertNull($statementImport->fresh()->rolled_back_at);
        $this->get(route('banking.index', ['account' => $account->id]))
            ->assertInertia(fn (Assert $page) => $page->where('imports.0.can_rollback', false));
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

    public function test_bank_movement_can_be_split_and_create_a_missing_custom_subaccount(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Split posting account', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $transaction = $account->transactions()->create(['booked_on' => now(), 'amount' => -100, 'currency' => 'EUR', 'description' => 'Unified bank charge', 'import_hash' => hash('sha256', 'split-bank-charge'), 'status' => 'pending']);

        $this->actingAs($user)->post(route('banking.transactions.post', $transaction), [
            'allocations' => [
                ['account_code' => '623', 'amount' => 40],
                ['account_code' => '6290009', 'account_name' => 'Other online services', 'amount' => 60],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $custom = LedgerAccount::where('code', '6290009')->firstOrFail();
        $this->assertSame('Other online services', $custom->name);
        $this->assertSame('629', $custom->parent_code);
        $this->assertSame('expense', $custom->type);
        $this->assertSame('custom', $custom->source);
        $this->assertSame(LedgerAccount::where('code', '629')->value('financial_category_id'), $custom->financial_category_id);
        $posting = BankTransactionPosting::with('journalEntry.lines.account')->firstOrFail();
        $this->assertNull($posting->counterpart_account_id);
        $this->assertCount(2, $posting->allocation_data);
        $this->assertSame('40.00', $posting->journalEntry->lines->firstWhere('account.code', '623')->debit);
        $this->assertSame('60.00', $posting->journalEntry->lines->firstWhere('account.code', '6290009')->debit);
        $this->assertSame('100.00', $posting->journalEntry->lines->firstWhere('account.code', '572')->credit);
        $this->assertSame('accounted', $transaction->fresh()->status);

        $this->post(route('banking.postings.reverse', $posting))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', $transaction->fresh()->status);
    }

    public function test_pending_movements_can_be_bulk_posted_atomically_to_one_account(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Bulk posting account', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $first = $account->transactions()->create(['booked_on' => '2026-08-01', 'amount' => -12.5, 'currency' => 'EUR', 'description' => 'Monthly bank fee', 'import_hash' => hash('sha256', 'bulk-fee-one'), 'status' => 'pending']);
        $second = $account->transactions()->create(['booked_on' => '2026-08-02', 'amount' => -7.5, 'currency' => 'EUR', 'description' => 'Transfer bank fee', 'import_hash' => hash('sha256', 'bulk-fee-two'), 'status' => 'pending']);
        $unselected = $account->transactions()->create(['booked_on' => '2026-08-03', 'amount' => 100, 'currency' => 'EUR', 'description' => 'Customer receipt', 'import_hash' => hash('sha256', 'bulk-unselected'), 'status' => 'pending']);

        $this->actingAs($user)->post(route('banking.transactions.bulk-post', $account), [
            'transaction_ids' => [$first->id, $second->id],
            'account_code' => '626001',
            'account_name' => 'Bank commissions',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('accounted', $first->fresh()->status);
        $this->assertSame('accounted', $second->fresh()->status);
        $this->assertSame('pending', $unselected->fresh()->status);
        $this->assertSame(2, BankTransactionPosting::count());
        $this->assertDatabaseHas('ledger_accounts', ['code' => '626001', 'name' => 'Bank commissions', 'source' => 'custom']);
        $this->assertSame(2, JournalEntry::where('entry_kind', 'bank_posting')->count());
        $this->assertEqualsWithDelta(20, JournalLine::whereHas('account', fn ($query) => $query->where('code', '626001'))->sum('debit'), 0.001);
    }

    public function test_bulk_posting_rejects_a_stale_selection_without_partial_changes(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Atomic bulk account', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $pending = $account->transactions()->create(['booked_on' => '2026-08-01', 'amount' => -10, 'currency' => 'EUR', 'description' => 'Pending fee', 'import_hash' => hash('sha256', 'atomic-pending'), 'status' => 'pending']);
        $processed = $account->transactions()->create(['booked_on' => '2026-08-02', 'amount' => -20, 'currency' => 'EUR', 'description' => 'Processed fee', 'import_hash' => hash('sha256', 'atomic-processed'), 'status' => 'accounted']);

        $this->actingAs($user)->post(route('banking.transactions.bulk-post', $account), [
            'transaction_ids' => [$pending->id, $processed->id],
            'account_code' => '626',
        ])->assertSessionHasErrors('transaction_ids');

        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame(0, BankTransactionPosting::count());
        $this->assertSame(0, JournalEntry::where('entry_kind', 'bank_posting')->count());
    }

    public function test_split_posting_rejects_an_unbalanced_allocation_and_unnamed_new_account(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Protected split account', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $transaction = $account->transactions()->create(['booked_on' => now(), 'amount' => -100, 'currency' => 'EUR', 'description' => 'Protected charge', 'import_hash' => hash('sha256', 'protected-split-charge'), 'status' => 'pending']);
        $this->actingAs($user);

        $this->post(route('banking.transactions.post', $transaction), ['allocations' => [
            ['account_code' => '623', 'amount' => 40],
            ['account_code' => '629', 'amount' => 50],
        ]])->assertSessionHasErrors('allocations');
        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertSame(0, BankTransactionPosting::count());

        $this->post(route('banking.transactions.post', $transaction), ['allocations' => [
            ['account_code' => '9990001', 'amount' => 100],
        ]])->assertSessionHasErrors('allocations.0.account_name');
        $this->assertDatabaseMissing('ledger_accounts', ['code' => '9990001']);
        $this->assertSame(0, BankTransactionPosting::count());
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
        $this->actingAs($user)->post(route('banking.accounts.store'), ['name' => 'Payments', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true])->assertRedirect();
        $account = BankAccount::where('name', 'Payments')->with('ledgerAccount')->firstOrFail();
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
        $this->assertSame(2, BankReconciliation::whereNotNull('journal_entry_id')->count());
        $this->assertSame(0, BankTransaction::where('status', 'pending')->count());
    }

    public function test_expense_reconciliation_with_a_bank_subaccount_can_be_undone_and_retried(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->post(route('banking.accounts.store'), ['name' => 'Expense bank', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true])->assertRedirect();
        $account = BankAccount::where('name', 'Expense bank')->with('ledgerAccount')->firstOrFail();
        $expense = Expense::create([
            'expense_category_id' => ExpenseCategory::firstOrFail()->id, 'created_by' => $user->id,
            'number' => 'EXP-RECONCILE', 'type' => 'receipt', 'merchant_name' => 'Test expense',
            'expense_date' => now(), 'subtotal' => 3.46, 'tax_amount' => 0.34,
            'amount' => 3.80, 'paid_amount' => 0, 'status' => 'pending',
        ]);
        $transaction = $account->transactions()->create([
            'booked_on' => now(), 'amount' => -3.80, 'currency' => 'EUR',
            'description' => 'Expense payment', 'import_hash' => hash('sha256', 'expense-retry'), 'status' => 'pending',
        ]);
        $this->post(route('banking.reconcile', $transaction), ['payment' => 'expense:'.$expense->id])->assertRedirect()->assertSessionHasNoErrors();
        $reconciliation = BankReconciliation::with('journalEntry.lines.account')->firstOrFail();
        $this->assertSame('3.80', $reconciliation->journalEntry->lines->firstWhere('account.code', $account->ledgerAccount->code)->credit);
        $this->assertSame('paid', $expense->fresh()->status);
        $this->delete(route('banking.reconciliations.undo', $reconciliation))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertSame('0.00', $expense->fresh()->paid_amount);
        $this->assertDatabaseCount('supplier_payments', 0);
        $this->post(route('banking.reconcile', $transaction), ['payment' => 'expense:'.$expense->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('paid', $expense->fresh()->status);
        $this->assertDatabaseCount('supplier_payments', 1);
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

    public function test_reconciliation_workspace_prioritizes_stale_unusual_and_unreferenced_movements(): void
    {
        $this->travelTo('2026-08-06');
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Review account', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        foreach (range(1, 5) as $index) {
            $account->transactions()->create([
                'booked_on' => today()->subDays($index),
                'amount' => 100,
                'currency' => 'EUR',
                'description' => 'Normal movement '.$index,
                'reference' => 'NORMAL-'.$index,
                'import_hash' => hash('sha256', 'normal-'.$index),
                'status' => 'accounted',
            ]);
        }
        $attention = $account->transactions()->create([
            'booked_on' => today()->subDays(10),
            'amount' => -5000,
            'currency' => 'EUR',
            'description' => 'Exceptional debit without supporting reference',
            'reference' => null,
            'import_hash' => hash('sha256', 'attention-movement'),
            'status' => 'pending',
        ]);

        $this->actingAs($user)->get(route('banking.index', ['account' => $account->id, 'status' => 'pending']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.stale', 1)
                ->where('summary.missingReference', 1)
                ->where('summary.unusualAmount', 1)
                ->where('summary.medianAmount', 100)
                ->where('transactions.data.0.id', $attention->id)
                ->where('transactions.data.0.match_quality', 'unmatched')
                ->where('transactions.data.0.risk_flags.0.code', 'stale')
                ->where('transactions.data.0.risk_flags.1.code', 'unusual_amount')
                ->where('transactions.data.0.risk_flags.2.code', 'missing_reference'));
    }

    public function test_reconciliation_workspace_preloads_candidates_without_per_transaction_queries(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Bulk candidate account', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        foreach (range(1, 25) as $index) {
            $account->transactions()->create([
                'booked_on' => today()->subDays($index % 5),
                'amount' => 100 + $index,
                'currency' => 'EUR',
                'description' => 'Bulk pending movement '.$index,
                'reference' => 'BULK-'.$index,
                'import_hash' => hash('sha256', 'bulk-candidate-'.$index),
                'status' => 'pending',
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('banking.index', ['account' => $account->id, 'status' => 'pending']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('transactions.data', 25));
        $candidateQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'sales_document_payments')
            || str_contains($query['query'], 'supplier_payments')
            || str_contains($query['query'], 'tax_return_payments')
            || str_contains($query['query'], 'sales_documents')
            || str_contains($query['query'], 'purchase_documents')
            || str_contains($query['query'], 'expenses'));
        DB::disableQueryLog();

        $this->assertLessThan(20, $candidateQueries->count(), 'Candidate lookup queries must remain constant per page, not grow per transaction.');
    }

    public function test_bulk_reconciliation_respects_the_selected_confidence_threshold(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $account = BankAccount::create(['name' => 'Strict reconciliation', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $sale = SalesDocument::create(['type' => 'invoice', 'number' => 'FAC-STRICT-1', 'status' => 'paid', 'issue_date' => now(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 0, 'amount' => 100, 'paid_amount' => 100, 'customer_snapshot' => []]);
        $sale->payments()->create(['created_by' => $user->id, 'paid_at' => now(), 'amount' => 100, 'method' => 'bank_transfer', 'reference' => 'STRICT-REF']);
        $transaction = $account->transactions()->create(['booked_on' => now(), 'amount' => 100, 'currency' => 'EUR', 'description' => 'Customer transfer', 'reference' => 'STRICT-REF', 'import_hash' => hash('sha256', 'strict-threshold'), 'status' => 'pending']);

        $this->actingAs($user)->post(route('banking.auto', $account), ['minimum_confidence' => 99])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('pending', $transaction->fresh()->status);

        $this->post(route('banking.auto', $account), ['minimum_confidence' => 98])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('reconciled', $transaction->fresh()->status);
        $this->assertDatabaseHas('bank_reconciliations', ['bank_transaction_id' => $transaction->id, 'method' => 'automatic']);
    }

    public function test_reusable_rules_preview_and_apply_safe_accounting_with_audited_trace(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user)->post(route('banking.accounts.store'), [
            'name' => 'Rules account',
            'currency' => 'EUR',
            'opening_balance' => 0,
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $account = BankAccount::where('name', 'Rules account')->firstOrFail();
        $fees = LedgerAccount::where('code', '626')->firstOrFail();

        $tooLarge = $account->transactions()->create([
            'booked_on' => '2026-08-01',
            'amount' => -150,
            'currency' => 'EUR',
            'description' => 'COMISION EXTRAORDINARIA',
            'reference' => 'FEE-LARGE',
            'import_hash' => hash('sha256', 'rule-large'),
            'status' => 'pending',
        ]);
        $incoming = $account->transactions()->create([
            'booked_on' => '2026-08-02',
            'amount' => 12.50,
            'currency' => 'EUR',
            'description' => 'DEVOLUCION COMISION',
            'reference' => 'FEE-IN',
            'import_hash' => hash('sha256', 'rule-incoming'),
            'status' => 'pending',
        ]);
        $matching = $account->transactions()->create([
            'booked_on' => '2026-08-03',
            'amount' => -12.50,
            'currency' => 'EUR',
            'description' => 'COMISIÓN MANTENIMIENTO CUENTA',
            'reference' => 'FEE-001',
            'import_hash' => hash('sha256', 'rule-match'),
            'status' => 'pending',
        ]);

        $this->post(route('banking.rules.store', $account), [
            'name' => 'Bank fees under 50',
            'ledger_account_id' => $fees->id,
            'priority' => 10,
            'direction' => 'out',
            'match_field' => 'description',
            'match_operator' => 'contains',
            'pattern' => 'comision',
            'min_amount' => 0,
            'max_amount' => 50,
            'confidence' => 98,
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $rule = BankReconciliationRule::firstOrFail();
        $versionOne = BankReconciliationRuleVersion::whereBelongsTo($rule, 'rule')->firstOrFail();
        $this->assertSame(1, $versionOne->version);
        $this->assertSame('comision', $versionOne->snapshot['pattern']);

        $this->get(route('banking.index', ['account' => $account->id, 'status' => 'pending']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reconciliationRules', 1)
                ->where('rulePreview.pending', 3)
                ->where('rulePreview.matched', 1)
                ->where('rulePreview.byRule.0.rule_id', $rule->id)
                ->where('rulePreview.byRule.0.matched', 1)
                ->where('transactions.data.0.id', $matching->id)
                ->where('transactions.data.0.rule_suggestion.rule_id', $rule->id)
                ->where('transactions.data.0.rule_suggestion.ledger_account.code', '626'));

        $this->post(route('banking.rules.apply', $account), [
            'minimum_confidence' => 95,
            'confirmation' => 'APPLY-2',
        ])->assertSessionHasErrors('confirmation');
        $this->assertSame('pending', $matching->fresh()->status);

        $this->post(route('banking.rules.apply', $account), [
            'minimum_confidence' => 95,
            'confirmation' => 'APPLY-1',
        ])->assertSessionHasNoErrors();

        $run = BankReconciliationRun::firstOrFail();
        $posting = BankTransactionPosting::where('bank_transaction_id', $matching->id)->firstOrFail();
        $this->assertSame('accounted', $matching->fresh()->status);
        $this->assertSame('pending', $tooLarge->fresh()->status);
        $this->assertSame('pending', $incoming->fresh()->status);
        $this->assertSame($rule->id, $posting->reconciliation_rule_id);
        $this->assertSame($versionOne->id, $posting->reconciliation_rule_version_id);
        $this->assertSame($run->id, $posting->reconciliation_run_id);
        $this->assertSame('completed', $run->status);
        $this->assertSame(1, $run->matched_count);
        $this->assertSame(2, $run->skipped_count);
        $this->assertSame(0, $run->failed_count);
        $this->assertDatabaseHas('bank_reconciliation_run_items', [
            'bank_reconciliation_run_id' => $run->id,
            'bank_reconciliation_rule_id' => $rule->id,
            'bank_reconciliation_rule_version_id' => $versionOne->id,
            'bank_transaction_id' => $matching->id,
            'outcome' => 'applied',
            'confidence' => 98,
        ]);
        $this->assertSame(1, $rule->fresh()->application_count);
        $this->assertEquals(
            $posting->journalEntry->lines()->sum('debit'),
            $posting->journalEntry->lines()->sum('credit'),
        );

        $this->post(route('banking.rule-runs.reverse', $run), [
            'confirmation' => 'REVERSE-'.$run->id.'-1',
            'reason' => 'The batch was posted to the wrong accounting period.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('pending', $matching->fresh()->status);
        $this->assertNotNull($posting->fresh()->reversed_at);
        $this->assertSame($posting->journal_entry_id, $posting->fresh()->reversalEntry->reverses_entry_id);
        $this->assertSame('reversed', $run->fresh()->status);
        $this->assertSame($user->id, $run->fresh()->reversed_by);
        $this->assertSame('The batch was posted to the wrong accounting period.', $run->fresh()->reversal_reason);

        $this->post(route('banking.rule-runs.reverse', $run), [
            'confirmation' => 'REVERSE-'.$run->id.'-1',
            'reason' => 'Attempt to reverse the same execution for a second time.',
        ])->assertSessionHasErrors('reason');

        $this->patch(route('banking.rules.update', $rule), [
            'name' => 'Bank fees under 50',
            'ledger_account_id' => $fees->id,
            'priority' => 10,
            'direction' => 'out',
            'match_field' => 'description',
            'match_operator' => 'contains',
            'pattern' => 'comision bancaria',
            'min_amount' => 0,
            'max_amount' => 50,
            'confidence' => 99,
            'is_active' => true,
            'change_reason' => 'Narrow the pattern after reviewing the first automatic run.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $rule->fresh()->current_version);
        $this->assertSame(2, $rule->versions()->count());
        $this->assertSame('comision bancaria', $rule->versions()->first()->snapshot['pattern']);
        $this->assertSame($versionOne->id, $posting->fresh()->reconciliation_rule_version_id);

        $this->get(route('banking.index', ['account' => $account->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('reconciliationRules.0.current_version', 2)
                ->has('reconciliationRules.0.versions', 2)
                ->where('reconciliationRules.0.versions.0.change_reason', 'Narrow the pattern after reviewing the first automatic run.'));

        $this->delete(route('banking.rules.destroy', $rule), ['reason' => 'Pause this rule while the bank description format is reviewed.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bank_reconciliation_rules', ['id' => $rule->id, 'is_active' => false]);
        $this->assertSame(3, $rule->fresh()->current_version);
        $this->assertSame(3, $rule->versions()->count());
    }

    public function test_conflicting_rules_are_explained_and_never_posted_automatically(): void
    {
        $user = User::factory()->create(['role' => 'commercial']);
        $this->actingAs($user)->post(route('banking.accounts.store'), [
            'name' => 'Conflict account',
            'currency' => 'EUR',
            'opening_balance' => 0,
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $account = BankAccount::where('name', 'Conflict account')->firstOrFail();
        $transaction = $account->transactions()->create([
            'booked_on' => '2026-08-04',
            'amount' => -25,
            'currency' => 'EUR',
            'description' => 'COMISION SERVICIO EMPRESA',
            'reference' => 'CONFLICT-1',
            'import_hash' => hash('sha256', 'rule-conflict'),
            'status' => 'pending',
        ]);
        $ruleData = [
            'priority' => 10,
            'direction' => 'out',
            'match_field' => 'description',
            'match_operator' => 'contains',
            'pattern' => 'comision',
            'confidence' => 98,
            'is_active' => true,
        ];
        $this->post(route('banking.rules.store', $account), [...$ruleData,
            'name' => 'Bank fees',
            'ledger_account_id' => LedgerAccount::where('code', '626')->value('id'),
        ])->assertSessionHasNoErrors();
        $this->post(route('banking.rules.store', $account), [...$ruleData,
            'name' => 'Other services fallback',
            'priority' => 20,
            'ledger_account_id' => LedgerAccount::where('code', '629')->value('id'),
        ])->assertSessionHasNoErrors();

        $this->get(route('banking.index', ['account' => $account->id, 'status' => 'pending']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rulePreview.matched', 0)
                ->where('rulePreview.conflicts', 1)
                ->where('rulePreview.details.0.transaction_id', $transaction->id)
                ->where('rulePreview.details.0.conflict', true)
                ->where('rulePreview.details.0.ledger_account.code', '626')
                ->where('rulePreview.details.0.alternatives.0.account_code', '629')
                ->where('transactions.data.0.rule_suggestion.conflict', true));

        $this->post(route('banking.rules.apply', $account), [
            'minimum_confidence' => 95,
            'confirmation' => 'APPLY-0',
        ])->assertSessionHasNoErrors();

        $run = BankReconciliationRun::firstOrFail();
        $this->assertSame(0, $run->matched_count);
        $this->assertSame(1, $run->skipped_count);
        $this->assertSame(1, $run->summary['conflicts']);
        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertDatabaseMissing('bank_transaction_postings', ['bank_transaction_id' => $transaction->id]);
        $this->assertDatabaseHas('bank_reconciliation_run_items', [
            'bank_reconciliation_run_id' => $run->id,
            'bank_transaction_id' => $transaction->id,
            'outcome' => 'conflict',
        ]);
    }
}
