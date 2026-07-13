<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\BankTransactionPosting;
use App\Models\BankTransfer;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
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
}
