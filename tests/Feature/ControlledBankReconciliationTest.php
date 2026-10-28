<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankStatementImport;
use App\Models\BankTransaction;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\SalesDocument;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Accounting\JournalPoster;
use App\Services\Banking\ControlledReconciliation;
use App\Services\Banking\ReconciliationWorkspace;
use App\Services\Finance\FinanceWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\Support\EconomicFixture;
use Tests\TestCase;

class ControlledBankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $amount = '100.00', string $bankAmount = '100.00'): array
    {
        $user = User::factory()->create(['role' => 'admin']);
        $account = BankAccount::create(['name' => 'SYN Bank', 'currency' => 'EUR', 'is_active' => true, 'opening_balance' => 0]);
        $bank = $account->transactions()->create(['booked_on' => '2026-09-21', 'amount' => $bankAmount, 'currency' => 'EUR', 'description' => 'SYN invoice', 'reference' => 'SYN-REF', 'import_hash' => hash('sha256', (string) Str::uuid()), 'status' => 'pending']);
        $document = SalesDocument::create(['type' => 'invoice', 'number' => 'SYN-'.Str::uuid(), 'status' => 'paid', 'locked_at' => now(), 'issue_date' => '2026-09-20', 'amount' => $amount, 'subtotal' => $amount, 'tax_amount' => 0, 'paid_amount' => $amount, 'customer_snapshot' => []]);
        $payment = $document->payments()->create(['amount' => $amount, 'paid_at' => '2026-09-21', 'method' => 'bank_transfer', 'reference' => 'SYN-REF']);
        app(AccountingSynchronizer::class)->syncCustomerPayment($payment, $user->id);
        $this->actingAs($user);

        return [$user, $account, $bank, $document, $payment];
    }

    private function matchData(BankTransaction $bank, string $payment, string $amount): array
    {
        return ['payment' => $payment, 'allocated_amount' => $amount, 'account_id' => $bank->bank_account_id, 'revision' => app(ControlledReconciliation::class)->revision($bank->fresh()), 'operation_key' => (string) Str::uuid(), 'reason' => 'Synthetic confirmed bank match', 'confirmed' => 1];
    }

    private function correction(): array
    {
        return ['operation_key' => (string) Str::uuid(), 'reason' => 'Synthetic incorrect bank allocation', 'confirmed' => 1];
    }

    public function test_exact_match_and_same_request_replay_keep_bank_and_erp_amounts(): void
    {
        [$user,$account,$bank,$document,$payment] = $this->fixture();
        $data = $this->matchData($bank, 'customer:'.$payment->id, '100.00');
        $url = route('banking.reconcile', $bank);
        $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('reconciled', $bank->fresh()->status);
        $this->assertSame('100.00', $bank->fresh()->amount);
        $this->assertSame('100.00', $document->fresh()->paid_amount);
        $this->assertSame('100.00', $payment->fresh()->amount);
        $this->post($url, [...$data, 'operation_key' => (string) Str::uuid(), 'revision' => app(ControlledReconciliation::class)->revision($bank->fresh())])->assertSessionHasErrors('reconciliation');
        $this->assertDatabaseCount('bank_reconciliations', 1);
        $this->assertSame(1, AuditLog::where('event', 'bank_match_confirmed')->count());
        $this->assertSame(0, app(FinanceWorkspace::class)->unmatchedPayments()->count());
    }

    public function test_one_receipt_can_be_matched_to_two_bank_movements_without_new_receipts(): void
    {
        [$user,$account,$bank,$document,$payment] = $this->fixture('100.00', '60.00');
        $this->post(route('banking.reconcile', $bank), $this->matchData($bank, 'customer:'.$payment->id, '60.00'))->assertSessionHasNoErrors();
        $this->assertSame('40.00', (string) app(ControlledReconciliation::class)->paymentRemaining($payment)->toScale(2));
        $this->assertEquals(40, app(FinanceWorkspace::class)->unmatchedPayments()->first()->amount);
        $other = $account->transactions()->create(['booked_on' => '2026-09-22', 'amount' => 40, 'currency' => 'EUR', 'description' => 'SYN partial', 'import_hash' => hash('sha256', 'partial-two'), 'status' => 'pending']);
        $this->post(route('banking.reconcile', $other), $this->matchData($other, 'customer:'.$payment->id, '40.00'))->assertSessionHasNoErrors();
        $this->assertSame(2, $payment->reconciliations()->count());
        $this->assertSame(0, app(FinanceWorkspace::class)->unmatchedPayments()->count());
        $this->assertDatabaseCount('sales_document_payments', 1);
    }

    public function test_one_bank_movement_can_match_two_recorded_partial_receipts(): void
    {
        [$user,$account,$bank,$document,$first] = $this->fixture('60.00', '100.00');
        $second = $document->payments()->create(['paid_at' => '2026-09-21', 'amount' => '40.00', 'method' => 'bank_transfer']);
        $this->post(route('banking.reconcile', $bank), $this->matchData($bank, 'customer:'.$first->id, '60.00'))->assertSessionHasNoErrors();
        $this->assertSame('partial', $bank->fresh()->status);
        $this->assertSame('40.00', (string) app(ControlledReconciliation::class)->bankRemaining($bank->fresh())->toScale(2));
        $this->post(route('banking.reconcile', $bank), $this->matchData($bank, 'customer:'.$second->id, '40.00'))->assertSessionHasNoErrors();
        $this->assertSame('reconciled', $bank->fresh()->status);
        $this->assertSame(2, $bank->reconciliations()->count());
    }

    public function test_partial_supplier_payment_and_account_isolation(): void
    {
        [$user,$account,$bank] = $this->fixture('100.00', '-60.00');
        $invoice = EconomicFixture::purchase(['supplier_id' => EconomicFixture::supplier(['legal_name' => 'SYN supplier', 'country' => 'ES'])->id, 'supplier_snapshot' => [], 'issue_date' => '2026-09-20', 'subtotal' => 100, 'tax_amount' => 0, 'type' => 'supplier_invoice', 'number' => 'SYN-PUR', 'status' => 'paid', 'amount' => 100, 'paid_amount' => 100]);
        $payment = SupplierPayment::create(['purchase_document_id' => $invoice->id, 'paid_at' => '2026-09-21', 'amount' => 100, 'method' => 'bank_transfer', 'funding_source_type' => 'bank_account', 'funding_source_id' => $account->id]);
        $this->post(route('banking.reconcile', $bank), $this->matchData($bank, 'supplier:'.$payment->id, '60.00'))->assertSessionHasNoErrors();
        $this->assertSame('40.00', (string) app(ControlledReconciliation::class)->paymentRemaining($payment)->toScale(2));
        $wrong = BankAccount::create(['name' => 'Wrong', 'currency' => 'EUR', 'opening_balance' => 0, 'is_active' => true]);
        $other = $wrong->transactions()->create(['booked_on' => '2026-09-21', 'amount' => -40, 'currency' => 'EUR', 'description' => 'SYN wrong account', 'import_hash' => hash('sha256', 'wrong'), 'status' => 'pending']);
        $this->post(route('banking.reconcile', $other), $this->matchData($other, 'supplier:'.$payment->id, '40.00'))->assertSessionHasErrors('reconciliation');
    }

    public function test_explicit_confirmation_bounds_revision_and_permissions(): void
    {
        [$user,$account,$bank,$document,$payment] = $this->fixture('100.00', '60.00');
        $url = route('banking.reconcile', $bank);
        $this->post($url, [])->assertSessionHasErrors(['payment', 'allocated_amount', 'operation_key', 'revision', 'confirmed']);
        $this->post($url, $this->matchData($bank, 'customer:'.$payment->id, '61.00'))->assertSessionHasErrors('reconciliation');
        $data = $this->matchData($bank, 'customer:'.$payment->id, '60.00');
        $this->post($url, [...$data, 'revision' => str_repeat('0', 64)])->assertSessionHasErrors('reconciliation');
        $this->actingAs(User::factory()->create(['role' => 'commercial']))->post($url, $data)->assertForbidden();
        $this->assertDatabaseCount('bank_reconciliations', 0);
    }

    public function test_voided_receipts_closed_periods_and_auto_matching_are_blocked(): void
    {
        [$user,$account,$bank,$document,$payment] = $this->fixture();
        $period = AccountingPeriod::create(['fiscal_year_id' => FiscalYear::where('year', 2026)->value('id'), 'year' => 2026, 'month' => 9, 'status' => 'closed']);
        $this->post(route('banking.reconcile', $bank), $this->matchData($bank, 'customer:'.$payment->id, '100.00'))->assertSessionHasErrors('accounting_period');
        $period->update(['status' => 'open']);
        $payment->forceFill(['voided_at' => now()->utc(), 'voided_by' => $user->id, 'void_reason' => 'SYN void', 'void_operation_key' => (string) Str::uuid()])->save();
        $this->post(route('banking.reconcile', $bank), $this->matchData($bank, 'customer:'.$payment->id, '100.00'))->assertSessionHasErrors('reconciliation');
        $this->post(route('banking.auto', $account))->assertSessionHasErrors('reconciliation');
        $this->assertDatabaseCount('bank_reconciliations', 0);
    }

    public function test_soft_correction_retains_original_and_reverses_only_reclassification(): void
    {
        [$user,$account,$bank,$document,$payment] = $this->fixture();
        $ledger = LedgerAccount::create(['code' => '572998', 'name' => 'SYN bank ledger', 'type' => 'asset', 'is_active' => true, 'is_postable' => true]);
        $account->update(['ledger_account_id' => $ledger->id]);
        $this->post(route('banking.reconcile', $bank), $this->matchData($bank, 'customer:'.$payment->id, '100.00'))->assertSessionHasNoErrors();
        $match = BankReconciliation::firstOrFail();
        $original = $match->journalEntry;
        $data = $this->correction();
        $url = route('banking.reconciliations.undo', $match->id);
        $this->delete($url, $data)->assertSessionHasNoErrors();
        $this->delete($url, $data)->assertSessionHasNoErrors();
        $retained = BankReconciliation::withoutGlobalScope('active_bank_reconciliation')->findOrFail($match->id);
        $this->assertNotNull($retained->reversed_at);
        $this->assertSame($data['reason'], $retained->reversal_reason);
        $this->assertSame('pending', $bank->fresh()->status);
        $this->assertSame('100.00', $payment->fresh()->amount);
        $this->assertSame($original->id, JournalEntry::findOrFail($retained->reversal_entry_id)->reverses_entry_id);
        $this->assertSame($match->id, $original->fresh()->source->id);
        $this->assertSame(1, AuditLog::where('event', 'bank_match_corrected')->count());
    }

    public function test_transactional_failure_does_not_leave_a_partial_match(): void
    {
        [$user,$account,$bank,$document,$payment] = $this->fixture();
        $ledger = LedgerAccount::create(['code' => '572997', 'name' => 'SYN bank', 'type' => 'asset', 'is_active' => true, 'is_postable' => true]);
        $account->update(['ledger_account_id' => $ledger->id]);
        $this->mock(JournalPoster::class)->shouldReceive('post')->once()->andThrow(new \RuntimeException('Synthetic failure'));
        $this->post(route('banking.reconcile', $bank), $this->matchData($bank, 'customer:'.$payment->id, '100.00'))->assertStatus(500);
        $this->assertDatabaseCount('bank_reconciliations', 0);
        $this->assertSame('pending', $bank->fresh()->status);
        $this->assertSame(0, AuditLog::where('event', 'bank_match_confirmed')->count());
    }

    public function test_ambiguous_suggestions_are_explained_and_never_confirmed(): void
    {
        [$user,$account,$bank,$document,$payment] = $this->fixture();
        $document->payments()->create(['paid_at' => '2026-09-21', 'amount' => 100, 'method' => 'bank_transfer', 'reference' => 'SYN-REF']);
        $workspace = app(ReconciliationWorkspace::class)->transaction($bank);
        $this->assertContains('ambiguous_reference', $workspace['flags']);
        $this->assertFalse($workspace['automatic_confirmation']);
        $this->assertContains('exact_reference', $workspace['suggestions'][0]['signals']);
        $this->assertDatabaseCount('bank_reconciliations', 0);
        $this->get(route('banking.reconciliation.candidates', $bank))->assertOk()->assertJsonPath('data.0.blocked', null);
        $this->get(route('banking.reconciliation.candidates', $bank).'?search='.urlencode($document->number))->assertOk()->assertJsonPath('data.0.number', $document->number);
    }

    public function test_reviews_have_history_and_cannot_fabricate_a_resolved_balance(): void
    {
        [$user,$account,$bank] = $this->fixture();
        $data = ['category' => 'bank_fee', 'review_status' => 'resolved', 'reason' => 'Synthetic bank fee investigation', 'operation_key' => (string) Str::uuid(), 'confirmed' => 1];
        $this->post(route('banking.discrepancies.review', $bank), $data)->assertSessionHasErrors('reconciliation');
        $data['review_status'] = 'investigating';
        $this->post(route('banking.discrepancies.review', $bank), $data)->assertSessionHasNoErrors();
        $this->post(route('banking.discrepancies.review', $bank), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, AuditLog::where('event', 'bank_discrepancy_review')->count());
        $this->assertSame('pending', $bank->fresh()->status);
    }

    public function test_corrected_reconciliation_history_prevents_statement_deletion(): void
    {
        [$user,$account,$bank,$document,$payment] = $this->fixture();
        $csv = "Fecha;Importe;Concepto;Referencia\n22/09/2026;100,00;SYN import;SYN-IMPORT\n";
        $this->post(route('banking.import', $account), ['statement' => UploadedFile::fake()->createWithContent('synthetic.csv', $csv)])->assertSessionHasNoErrors();
        $import = BankStatementImport::firstOrFail();
        $imported = $import->transactions()->firstOrFail();
        $this->post(route('banking.reconcile', $imported), $this->matchData($imported, 'customer:'.$payment->id, '100.00'))->assertSessionHasNoErrors();
        $match = BankReconciliation::firstOrFail();
        $this->delete(route('banking.reconciliations.undo', $match->id), $this->correction())->assertSessionHasNoErrors();
        $this->delete(route('banking.imports.rollback', $import), ['reason' => 'Synthetic import correction'])->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('bank_transactions', ['id' => $imported->id]);
        $this->assertDatabaseHas('bank_reconciliations', ['id' => $match->id]);
    }

    public function test_resolved_review_requires_financial_evidence_and_correction_requires_specific_permission(): void
    {
        [$user,$account,$bank,$document,$payment] = $this->fixture();
        $bank->update(['status' => 'reconciled']); // Synthetic inconsistent legacy state, no actual relationship.
        $this->post(route('banking.discrepancies.review', $bank), ['category' => 'already_reconciled', 'review_status' => 'resolved', 'reason' => 'Synthetic evidence review', 'operation_key' => (string) Str::uuid(), 'confirmed' => 1])->assertSessionHasErrors('reconciliation');
        $bank->update(['status' => 'pending']);
        $this->post(route('banking.reconcile', $bank), $this->matchData($bank, 'customer:'.$payment->id, '100.00'))->assertSessionHasNoErrors();
        $match = BankReconciliation::firstOrFail();
        $this->actingAs(User::factory()->create(['role' => 'commercial']))->delete(route('banking.reconciliations.undo', $match->id), $this->correction())->assertForbidden();
        $this->assertNull($match->fresh()->reversed_at);
    }

    public function test_new_confirmations_keep_an_unambiguous_utc_instant_during_the_repeated_hour(): void
    {
        foreach (['2026-10-25T00:30:00Z', '2026-10-25T01:30:00Z'] as $instant) {
            $this->travelTo(CarbonImmutable::parse($instant));
            [$user,$account,$bank,$document,$payment] = $this->fixture();
            $this->post(route('banking.reconcile', $bank), $this->matchData($bank, 'customer:'.$payment->id, '100.00'))->assertSessionHasNoErrors();
            $row = $bank->reconciliations()->firstOrFail();
            $this->assertSame(CarbonImmutable::parse($instant)->toISOString(), $row->confirmed_at_utc->toISOString());
            $this->assertSame($row->confirmed_at_utc->toISOString(), app(ReconciliationWorkspace::class)->transaction($bank->fresh())['history'][0]['reconciled_at']);
        }
    }
}
